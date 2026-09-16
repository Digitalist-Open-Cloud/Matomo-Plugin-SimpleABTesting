# Changelog

## [0.1.95] - 2026-09-16

### Added

- `updateExperiment` API/DAO method, so editing an experiment no longer means
  delete-and-reinsert.
- `getExperimentGoalData` API method, exposing the new per-goal conversion
  record.
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
  data spanned a week/month archive.

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
