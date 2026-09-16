<?php

namespace Piwik\Plugins\SimpleABTesting;

use Piwik\Archive\ArchiveInvalidator;
use Piwik\Common;
use Piwik\Date;
use Piwik\Db;
use Piwik\Updater;
use Piwik\Updates as PiwikUpdates;

/**
 * 0.1.96 — invalidate this plugin's existing archives so they are recomputed
 * in the new row shape.
 *
 * This branch changed the row shape of Archiver::RECORD_NAME (and
 * RECORD_NAME_GOALS): the top-level (and, for goals, middle-level) row now
 * carries a real summed nb_visits/nb_conversions/etc. metric instead of only
 * a label — the actual numbers used to live exclusively one level down, in
 * the subtable. An already-installed instance keeps whatever it already
 * archived under the OLD shape until those archives are invalidated and
 * recomputed; without this file they would silently stay stale (a visible
 * top-level row with an empty metric) forever, since Matomo only calls a
 * plugin's install() on its very first activation, never again, and never
 * re-archives on its own just because the plugin code changed.
 *
 * No database schema changed, so this overrides doUpdate() directly rather
 * than getMigrations() (see Piwik\Updates's own docblock: getMigrations() is
 * for SQL migrations, doUpdate() for everything else).
 *
 * Why 0.1.96 and not 0.1.95: plugin.json was already published as 0.1.95
 * earlier on this branch, before any Updates/ file existed. Matomo records
 * the installed version in matomo_option.version_SimpleABTesting and only
 * runs an Updates_x_y_z class for versions it has not already recorded as
 * done, so an instance that ever saw the earlier 0.1.95 build would never
 * have executed an Updates/0.1.95.php added afterwards. The version number
 * had to move forward for the update to be reachable at all.
 *
 * Scope of the invalidation — deliberately data-driven, not a blanket sweep:
 *
 * - Sites come from the plugin's OWN log table, not from
 *   SitesManager::getAllSitesId(). A site that has never recorded a single
 *   SimpleABTesting hit has no legacy archive in the old shape, so there is
 *   nothing to fix for it and invalidating it is pure waste. A fresh install
 *   (zero rows in the table) therefore invalidates nothing at all, which is
 *   the correct no-op.
 * - Dates come from each site's own MIN/MAX server_time, one Date per day,
 *   with the upper bound clamped to today so no future-dated period is ever
 *   queued. period='day' with $cascadeDown=true is the same shape Matomo
 *   core itself uses when a plugin needs its reports re-archived
 *   (ArchiveInvalidator::reArchiveReport(), which builds one Date per day up
 *   to Date::today() and invalidates period='day'): a day period has no
 *   children, so the cascade resolves to
 *   ArchiveInvalidator::addParentPeriodsByYearMonth(), pulling the
 *   containing week, month and year in with it — the rollups get fixed
 *   without enumerating every other day inside them.
 *
 * The earlier draft of this file invalidated period='year' for every year
 * from 2010 to the current year, for every site. That expands (via
 * cascadeDown) to roughly 430 period rows per site-year — tens of thousands
 * of queued recomputations from one point release on an instance with older
 * sites — and, because a 'year' period always spans the whole calendar year,
 * it necessarily queued future-dated days, weeks and months for the current
 * year. Neither is acceptable on a real instance.
 *
 * markArchivesAsInvalidated() does NOT delete any data — confirmed against
 * Matomo core (core/Archive/ArchiveInvalidator.php) and against the console
 * command that wraps it (plugins/CoreAdminHome/Commands/
 * InvalidateReportData.php): it only marks archives for recomputation on
 * next access. $name scopes the invalidation to this plugin only
 * ($forceInvalidateNonexistentRanges=false, matching core's own default).
 */
class Updates_0_1_96 extends PiwikUpdates
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

    /**
     * Which sites have SimpleABTesting data at all, and what date range each
     * one covers. Same raw Piwik\Db access the plugin's Dao classes already
     * use. Returns an empty array on a fresh install with no experiments ever
     * run — in which case there is nothing to invalidate.
     *
     * @return array<int, array{min: string, max: string}>
     */
    private function getLoggedDateRangePerSite(): array
    {
        $sql = 'SELECT idsite, MIN(server_time) AS min_time, MAX(server_time) AS max_time
                  FROM ' . Common::prefixTable('simple_ab_testing_log') . '
                 GROUP BY idsite';

        $ranges = [];

        foreach (Db::fetchAll($sql) as $row) {
            // A site can only appear here because it has rows, but server_time
            // is only NOT NULL by table definition — be explicit rather than
            // feeding a null into Date::factory().
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

    /**
     * One Date per day from $minTime's day through $maxTime's day, with the
     * upper bound clamped to $today so a future-dated log row (dev fixtures,
     * a client clock skew) can never make this queue archiving for periods
     * that have not happened yet.
     *
     * @return Date[]
     */
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
