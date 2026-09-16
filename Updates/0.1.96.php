<?php

namespace Piwik\Plugins\SimpleABTesting;

use Piwik\Archive\ArchiveInvalidator;
use Piwik\Date;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;
use Piwik\Updater;
use Piwik\Updates as PiwikUpdates;

/**
 * 0.1.96 — invalidate all existing SimpleABTesting archives.
 *
 * Why 0.1.96 and not 0.1.95: plugin.json was already published as 0.1.95
 * earlier on this branch, before any Updates/ file existed. Matomo records
 * the installed version in matomo_option.version_SimpleABTesting and only
 * runs an Updates_x_y_z class for a version it has not already recorded as
 * done, so an instance that ever saw the earlier 0.1.95 build would never
 * have executed an Updates/0.1.95.php added afterwards. Matomo matches
 * update files by filename-as-version, so the filename, the class name and
 * plugin.json all had to move forward together for the update to be
 * reachable at all.
 *
 * This release changed the row shape of Archiver::RECORD_NAME (and
 * RECORD_NAME_GOALS): the top-level (and, for goals, middle-level) row now
 * carries a real summed nb_visits/nb_unique_visitors/etc. metric instead of
 * only a label — the actual numbers used to live exclusively one level
 * down, in the subtable. An already-installed instance keeps whatever it
 * already archived under the OLD shape until those archives are
 * invalidated and recomputed; without this file they would silently stay
 * stale (a visible top-level row with an empty/zero metric) forever, since
 * Matomo only calls a plugin's install() on its very first activation, never
 * again, and never re-archives on its own just because the plugin code
 * changed.
 *
 * No database schema changed in this release, so this overrides doUpdate()
 * directly rather than getMigrations() (see Piwik\Updates's own docblock:
 * getMigrations() is for SQL migrations, doUpdate() for everything else).
 *
 * markArchivesAsInvalidated() does NOT delete any data — confirmed against
 * Matomo core (core/Archive/ArchiveInvalidator.php) and against the real
 * console command that wraps it (plugins/CoreAdminHome/Commands/
 * InvalidateReportData.php, the implementation behind
 * `./console core:invalidate-report-data`): it only marks archives for
 * recomputation on next access. $dates must be a list of Date objects, one
 * per period instance to invalidate (each entry becomes exactly one
 * `$period`-typed period via ArchiveInvalidator::makePeriod()) — NOT a
 * [from, to] boundary pair, confirmed by reading
 * ArchiveInvalidator::getAllPeriodsByYearMonth(), which iterates $dates with
 * `foreach ($dates as $date) { $periodObj = $this->makePeriod($date,
 * $period); ... }`. So this builds one Date per calendar year in a broad
 * range and invalidates period=year with $cascadeDown=true, which recurses
 * into every month/day under each year (addChildPeriodsByYearMonth()) —
 * confirmed the same way. $name scopes the invalidation to this plugin only
 * ($forceInvalidateNonexistentRanges=false, matching core's own default).
 */
class Updates_0_1_96 extends PiwikUpdates
{
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
        $idSites = SitesManagerAPI::getInstance()->getAllSitesId();
        if (empty($idSites)) {
            return;
        }

        // One Date per calendar year, 2010 through the current year — broad
        // enough to cover any plausible existing SimpleABTesting data, cheap
        // enough that the extra years cost nothing (cascadeDown then walks
        // each year down to its months/days).
        $dates = [];
        $currentYear = (int) Date::now()->toString('Y');
        for ($year = 2010; $year <= $currentYear; $year++) {
            $dates[] = Date::factory($year . '-01-01');
        }

        $this->invalidator->markArchivesAsInvalidated(
            $idSites,
            $dates,
            'year',
            null,
            $cascadeDown = true,
            $forceInvalidateNonexistentRanges = false,
            $name = 'SimpleABTesting'
        );
    }
}
