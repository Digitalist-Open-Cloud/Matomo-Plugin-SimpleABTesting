<?php

namespace Piwik\Plugins\SimpleABTesting;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Plugin\Archiver as MatomoArchiver;
use Piwik\Db;
use Piwik\Log\LoggerInterface;
use Piwik\Container\StaticContainer;

class Archiver extends MatomoArchiver
{
    const RECORD_NAME = 'SimpleABTesting_ExperimentData';
    const RECORD_NAME_UNIQUE_VISITORS = 'SimpleABTesting_ExperimentUniqueVisitors';
    const RECORD_NAME_GOALS = 'SimpleABTesting_ExperimentGoals';
    const DIMENSION = 'experiment_name';

    public function aggregateDayReport()
    {
        /** @var LoggerInterface $logger */
        $logger = StaticContainer::get('Psr\Log\LoggerInterface');
        $logger->debug('SimpleABTesting: Starting day report aggregation.');

        $params = $this->getProcessor()->getParams();
        $idSite  = $this->getProcessor()->getParams()->getSite()->getId();
        $dateStart = $params->getDateStart()->toString('Y-m-d 00:00:00');
        $dateEnd = $params->getDateEnd()->toString('Y-m-d 23:59:59');

        $logger->debug("SimpleABTesting: Archiving params: idSite={$idSite}, dateStart={$dateStart}, dateEnd={$dateEnd}");

        // Visits: COUNT(DISTINCT idvisit), not COUNT(*) — the log table has
        // one row per tracking request, not per visit, so COUNT(*) overcounts
        // by however many pageviews/events happened during the visit.
        $visitsQuery = "
            SELECT
                experiment_name AS label,
                variant AS variant,
                COUNT(DISTINCT idvisit) AS nb_visits
            FROM " . Common::prefixTable('simple_ab_testing_log') . "
            WHERE idsite = ?
            AND server_time BETWEEN ? AND ?
            GROUP BY experiment_name, variant
        ";
        $visitRows = Db::fetchAll($visitsQuery, [$idSite, $dateStart, $dateEnd]);
        $this->getProcessor()->insertBlobRecord(
            self::RECORD_NAME,
            $this->buildOneLevelTable($visitRows, 'variant', ['nb_visits'])->getSerialized()
        );

        // Unique visitors: its OWN record, day-only. A browser seen on
        // several days would be double-counted if this were summed into a
        // week/month the way nb_visits safely is — see recordNamesForMultiPeriod().
        $uniqueVisitorsQuery = "
            SELECT
                experiment_name AS label,
                variant AS variant,
                COUNT(DISTINCT idvisitor) AS nb_unique_visitors
            FROM " . Common::prefixTable('simple_ab_testing_log') . "
            WHERE idsite = ?
            AND server_time BETWEEN ? AND ?
            GROUP BY experiment_name, variant
        ";
        $uniqueVisitorRows = Db::fetchAll($uniqueVisitorsQuery, [$idSite, $dateStart, $dateEnd]);
        $this->getProcessor()->insertBlobRecord(
            self::RECORD_NAME_UNIQUE_VISITORS,
            $this->buildOneLevelTable($uniqueVisitorRows, 'variant', ['nb_unique_visitors'])->getSerialized()
        );

        // Goals: which of the experiment's visits converted, and how many
        // times. The subquery collapses the log table to one row per
        // (idvisit, variant) BEFORE joining log_conversion — without it, a
        // visit logged on N tracking requests joins N times and inflates
        // nb_conversions by a factor of N. Confirmed live against real
        // fixture data: 3 log rows for one visit that converted once
        // produced nb_conversions=3 until this dedup subquery was added.
        $goalsQuery = "
            SELECT
                v.experiment_name AS label,
                v.variant AS variant,
                c.idgoal AS idgoal,
                COUNT(DISTINCT c.idvisit) AS nb_visits_converted,
                COUNT(*) AS nb_conversions
            FROM (
                SELECT DISTINCT idsite, idvisit, experiment_name, variant
                FROM " . Common::prefixTable('simple_ab_testing_log') . "
                WHERE idsite = ?
                AND server_time BETWEEN ? AND ?
            ) v
            INNER JOIN " . Common::prefixTable('log_conversion') . " c
                ON c.idvisit = v.idvisit AND c.idsite = v.idsite
            GROUP BY v.experiment_name, v.variant, c.idgoal
        ";
        $goalRows = Db::fetchAll($goalsQuery, [$idSite, $dateStart, $dateEnd]);
        $this->getProcessor()->insertBlobRecord(
            self::RECORD_NAME_GOALS,
            $this->buildTwoLevelTable($goalRows, 'variant', 'idgoal', ['nb_visits_converted', 'nb_conversions'])->getSerialized()
        );

        if (empty($visitRows) && empty($uniqueVisitorRows) && empty($goalRows)) {
            $logger->debug("SimpleABTesting: No rows fetched for site: {$idSite}");
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param string[] $metricColumns
     */
    private function buildOneLevelTable(array $rows, string $identityColumn, array $metricColumns): DataTable
    {
        $grouped = RowGrouper::groupByOneLevel($rows, $identityColumn, $metricColumns);
        $table = new DataTable();
        foreach ($grouped as $label => $identityRows) {
            $topRow = new Row([Row::COLUMNS => ['label' => $label]]);
            $subtable = new DataTable();
            foreach ($identityRows as $identityValue => $metrics) {
                $subtable->addRowFromSimpleArray(array_merge(['label' => $identityValue], $metrics));
            }
            $topRow->setSubtable($subtable);
            $table->addRow($topRow);
        }
        return $table;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param string[] $metricColumns
     */
    private function buildTwoLevelTable(array $rows, string $outerIdentityColumn, string $innerIdentityColumn, array $metricColumns): DataTable
    {
        $grouped = RowGrouper::groupByTwoLevels($rows, $outerIdentityColumn, $innerIdentityColumn, $metricColumns);
        $table = new DataTable();
        foreach ($grouped as $label => $outerRows) {
            $topRow = new Row([Row::COLUMNS => ['label' => $label]]);
            $outerTable = new DataTable();
            foreach ($outerRows as $outerValue => $innerRows) {
                $outerRow = new Row([Row::COLUMNS => ['label' => $outerValue]]);
                $innerTable = new DataTable();
                foreach ($innerRows as $innerValue => $metrics) {
                    $innerTable->addRowFromSimpleArray(array_merge(['label' => $innerValue], $metrics));
                }
                $outerRow->setSubtable($innerTable);
                $outerTable->addRow($outerRow);
            }
            $topRow->setSubtable($outerTable);
            $table->addRow($topRow);
        }
        return $table;
    }

    /**
     * Which archive records are valid to sum across sub-periods (day -> week
     * -> month -> year). A pure, no-Matomo-calls decision so it can be
     * unit-tested directly.
     *
     * Visits and conversions are summed across periods, same as Matomo's own
     * nb_visits everywhere. Unique VISITORS are not (the same browser across
     * several days would be counted once per day) — RECORD_NAME_UNIQUE_VISITORS
     * is deliberately absent here, so Matomo's archiving stores no multi-period
     * blob for it rather than a plausible-looking wrong sum.
     *
     * Known limitation, inherited from bucketing by server_time (a per-
     * tracking-request timestamp) rather than by the visit's own day: a visit
     * that spans midnight emits log rows on both days, so its visit and
     * conversion counts can each be counted once per day it touches, inflating
     * a week/month rollup by one for that visit. Narrower than the per-hit
     * multiplication this task's queries fix (bounded to midnight-crossing
     * visits, not every tracking hit), and not addressed here — a real fix
     * would bucket by log_visit.visit_last_action_time instead, which needs
     * a join this plugin's queries don't currently do.
     *
     * @return string[]
     */
    public static function recordNamesForMultiPeriod(): array
    {
        return [self::RECORD_NAME, self::RECORD_NAME_GOALS];
    }

    /**
     * Aggregate data across multiple periods (e.g., week, month, year).
     */
    public function aggregateMultipleReports()
    {
        $this->getProcessor()->aggregateDataTableRecords(self::recordNamesForMultiPeriod());
    }
}
