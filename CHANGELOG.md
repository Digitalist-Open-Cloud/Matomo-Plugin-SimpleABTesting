# Changelog

## [0.1.98] - 2026-10-17

### Added

- Assignment is stored in `localStorage` under `sabt:<idexperiment>` as JSON
  with the variant `v`, an assignment id `a` (32 hex characters) and an expiry
  `exp`, replacing the `sabt_<name>` cookie.
- The tag sends the assignment id `saba` together with `sabt`, `sabi` and
  `sabn`, and sends an exposure `ping` after the variant has been applied, for
  both variants. Exposure is therefore recorded with a timestamp even on
  single-pageview visits.
- `tracker.js` reads the assignment from `localStorage` and sends `sabt`,
  `sabi` and `saba` on every request type the Matomo tracker sends through a
  plugin hook (pageviews, links, goals, events, site search, ecommerce, pings
  and content tracking) while an assignment is active.
- `simple_ab_testing_log` gets the columns `idexperiment` and `assignment_id`
  and the index `idx_visit_time (idvisit, server_time)`, added by
  `Updates/0.1.98.php`. A request-supplied `sabi` is verified against the
  request's site and otherwise resolved by experiment name; malformed `saba`,
  `sabi` and `sabt` values are stored as NULL or ignored.

### Changed

- **Upgrade note:** when `localStorage` is unavailable, the original is shown
  and the visitor is not part of the test.
- Only one assignment is kept per site: when the tag applies an experiment it
  removes every other `sabt:*` entry, so a concluded test's assignment stops
  riding along on the visitor's requests.

### Fixed

- Requests whose UTC day lies outside the experiment's `from_date`–`to_date`
  (for example a returning visitor after the test was concluded and its
  `to_date` moved back) are logged with `idexperiment` NULL, so they no longer
  count for the concluded test.

### Security

- Experiment rows are scoped to their site: `insertExperiment`,
  `updateExperiment`, `getExperiment` and `deleteExperiment` require admin
  access to the given site (not just to some site); `deleteExperiment` now
  takes `idSite` (`deleteExperiment(int $id, int $idSite)`); updates and
  deletes filter on `id` and `idsite`, and an update never changes a row's
  `idsite`. The Controller's write actions check admin access to the site
  before acting.
- `API::getExperimentPublic` now requires view access to the site and returns
  only the public tag fields. The tag endpoint in the Controller reads the row
  directly by id and idsite.

## [0.1.96] - 2026-09-16

### Added

- `Updates/0.1.96.php`: invalidates this plugin's existing archives on
  upgrade. **Upgrade note:** 0.1.95's row shape (nested subtables with summed
  parent-row metrics) only applies to archives computed after this update
  runs — this file makes sure existing installations recompute rather than
  keep serving the old flat shape indefinitely. Invalidation only marks
  archives for recomputation on next access; it does not delete any data.

### Fixed

- **Version numbering.** The archive-invalidation update above was first
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
- `doUpdate()` skips the invalidation when the plugin is not activated.
  `ArchiveInvalidator::markArchivesAsInvalidated()` throws in that case, and
  `Updater::update()` turns any exception from `doUpdate()` into a warning
  while marking the component successfully updated anyway — so the failure
  would have been silent and the version bumped regardless. The no-op is now
  deliberate.

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
