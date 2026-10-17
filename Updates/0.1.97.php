<?php

namespace Piwik\Plugins\SimpleABTesting;

use Piwik\Archive\ArchiveInvalidator;
use Piwik\Common;
use Piwik\Date;
use Piwik\Db;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Updater;
use Piwik\Updates as PiwikUpdates;

/**
 * 0.1.97 — add visit-scoped tracking for experiment variant and name.
 *
 * This version adds three new log_visit columns via Matomo Dimensions:
 * - variant (from IsExperiment dimension)
 * - experiment_name (from ExperimentName dimension)
 * - experiment_id (from ExperimentCount dimension)
 *
 * Since these capture tracker data on each visit (via onNewVisit/onExistingVisit
 * hooks), already-archived data won't include them until archives are
 * invalidated and recomputed. This follows the same pattern as 0.1.96.
 */
class Updates_0_1_97 extends PiwikUpdates
{
    private const PLUGIN_NAME = 'SimpleABTesting';

    /**
     * @var ArchiveInvalidator
     */
    private $invalidator;

    public function __construct(ArchiveInvalidator $invalidator)
    {
        $this->invalidator = $invalidator;
    }

    public function doUpdate(Updater $updater)
    {
        if (!PluginManager::getInstance()->isPluginActivated(self::PLUGIN_NAME)) {
            return;
        }

        $today = Date::today();

        foreach ($this->getLoggedDateRangePerSite() as $idSite => $range) {
            $dates = $this->buildDayDates($range['min'], $range['max'], $today);

            if (empty($dates)) {
                continue;
            }

            $this->invalidator->markArchivesAsInvalidated(
                [$idSite],
                $dates,
                'day',
                null,
                $cascadeDown = true,
                $forceInvalidateNonexistentRanges = false,
                $name = self::PLUGIN_NAME
            );
        }
    }

    private function getLoggedDateRangePerSite(): array
    {
        $sql = 'SELECT idsite, MIN(server_time) AS min_time, MAX(server_time) AS max_time
                  FROM ' . Common::prefixTable('simple_ab_testing_log') . '
                 GROUP BY idsite';

        $ranges = [];

        foreach (Db::fetchAll($sql) as $row) {
            if (empty($row['min_time']) || empty($row['max_time'])) {
                continue;
            }

            $ranges[(int) $row['idsite']] = [
                'min' => (string) $row['min_time'],
                'max' => (string) $row['max_time'],
            ];
        }

        return $ranges;
    }

    private function buildDayDates(string $minTime, string $maxTime, Date $today): array
    {
        $date = Date::factory(substr($minTime, 0, 10));
        $end  = Date::factory(substr($maxTime, 0, 10));

        if ($end->isLater($today)) {
            $end = $today;
        }

        if ($date->isLater($end)) {
            return [];
        }

        $dates = [];

        while (!$date->isLater($end)) {
            $dates[] = $date;
            $date = $date->addDay(1);
        }

        return $dates;
    }
}
