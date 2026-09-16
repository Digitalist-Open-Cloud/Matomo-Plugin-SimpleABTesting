# Changelog

## [0.1.96] - 2026-09-16

### Fixed

- **Version numbering.** The archive-invalidation update below was first
  written as `Updates/0.1.95.php`, but `plugin.json` had already been bumped
  to 0.1.95 before that file existed. Matomo only executes an `Updates_x_y_z`
  class for a version it has not already recorded in
  `matomo_option.version_SimpleABTesting`, so on any instance that had seen
  the earlier 0.1.95 build the update would have been skipped without a word.
  Renamed to 0.1.96 — file, class and `plugin.json` together, since Matomo
  matches update files by filename-as-version.
- The invalidation is now derived from the plugin's own data instead of
  sweeping every year from 2010 to the current year for every site. It looks
  up which sites have `simple_ab_testing_log` rows and what date range each
  one covers, and invalidates one day per day in that range, clamped so the
  upper bound never exceeds today. The old sweep expanded to roughly 430
  queued period recomputations per site-year and, because a `year` period
  always spans the whole calendar year, queued future-dated days, weeks and
  months as well. A site with no SimpleABTesting data now correctly has
  nothing invalidated.

## [0.1.95] - 2026-09-16

### Added

- `updateExperiment` API/DAO method, so editing an experiment no longer means
  delete-and-reinsert.
- `getExperimentGoalData` API method, exposing the new per-goal conversion
  record.
- `getExperimentUniqueVisitorData` API method, exposing the previously
  unread unique-visitors record (day-only — see `Archiver::recordNamesForMultiPeriod`).
- A concurrent-experiment guard: a new experiment on a site whose date range
  overlaps an existing one on that site is now rejected.
- Experiment names containing a comma are rejected (the Tag Manager tag's
  parameter string is comma-joined and never URL-encodes the name).
- `Updates/0.1.96.php`: invalidates all existing `SimpleABTesting` archives on
  upgrade. **Upgrade note:** the row shape below (nested subtables with
  summed parent-row metrics) only applies to archives computed after this
  update runs — this file makes sure existing installations recompute
  rather than keep serving the old flat shape indefinitely. Invalidation
  only marks archives for recomputation on next access; it does not delete
  any data.

### Changed

- `nb_visits` now counts distinct visits (`COUNT(DISTINCT idvisit)`), not
  tracking hits (`COUNT(*)`) — affected both the archiver and the raw
  `getExperimentReportData` method.
- Goal conversions are now deduplicated against the log table before joining
  `log_conversion`, so a visit logged on multiple tracking hits no longer
  inflates its conversion count.
- `insertExperiment`/`deleteExperiment` take `int`, not `bool`, for `idSite`/`id`.
- Report rows now nest variant (and, for goals, the goal id) as subtables
  instead of flat columns — a flat `variant` column was summed into a
  meaningless number by Matomo's own period rollup as soon as an experiment's
  data spanned a week/month archive. The top-level (and, for goals, the
  middle/variant-level) row now also carries the real SUM of its subtable's
  metrics, so the visible row shows real numbers without needing to expand
  it.
- `Controller::getExperimentReport()` — the actual live render path Matomo
  dispatches to — now shows the same corrected columns
  (`label`, `nb_visits`) as `Reports/GetExperimentReport.php`'s own view
  config.
- `Controller::addExperiment()` now redirects with the real validation/
  overlap error message on failure instead of a raw error page.

### Deprecated

### Removed

- Dead, commented-out `trackEvent` calls in the tag's JS (and the
  `originalName` parameter that only fed them).

### Fixed

- Coding standard
- Unique visitors are no longer summed across a multi-day period (a browser
  seen on three different days was counted as three) — moved to its own,
  day-only archive record.

### Security
