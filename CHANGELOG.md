# Changelog

All notable changes to the ScaleAQ Reporting plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-03-26

### Added

- Multi-company select filter — choose multiple companies simultaneously with checkbox dropdown in both reports
- "Select All" option in company filter to quickly select/deselect all companies
- Clickable drill-down on Completed and Not Completed stat cards — click the number to reveal a user name list
- Per-group drill-down in the Group Completion Rates table — click completed/not completed counts to see users in that group
- "Not Completed" column added to group completion table for quick reference
- Drill-down tables show: First Name, Last Name, Email, Company (and Completed Date for completed users)

### Changed

- Company filter changed from single-select dropdown to multi-select checkbox dropdown
- Statistics aggregate correctly across multiple companies (sum of counts, not average of percentages)
- URL parameter `cr_company` / `ur_company` now accepts arrays (`cr_company[]=X&cr_company[]=Y`)
- Export URLs correctly encode multi-company selections
- Backward compatible — old single-company URLs (`?cr_company=X`) still work

## [1.2.0] - 2026-03-09

### Changed

- Period filter changed from date range (from-to) to cumulative cutoff date — completions are now counted up to the cutoff, so earlier completions are no longer excluded
- Presets updated: "By end of 2025", "By end of 2024", "Custom cutoff date" (replaces "Last 12 months", "Last year", "Custom range")
- Filter UI shows single "Cutoff date" input instead of From/To date range when using custom period
- Labels updated: "Completed by cutoff" / "Not completed by cutoff" instead of "in period" / "Not in period"
- Subtitle updated: "Showing completions recorded by: {date}" instead of "during"

### Removed

- `from` date parameter (`cr_from` / `ur_from`) — no longer used in queries or export URLs

## [1.1.0] - 2026-03-08

### Added

- Time period filter presets: All time, Last 12 months, Last year, Custom range (both reports)
- "Completed Date" column in user report table and CSV export
- "Completed Date" column in course report CSV export
- Completions by Company donut chart (replaces horizontal bar chart) — only shows companies with completions
- Period-aware UI labels: "Completed in period" / "Not in period" when date filtering is active
- Descriptive subtitle explaining what the selected time period means

### Changed

- Report layout now uses full available width instead of max 1120px
- All critical UI styles use inline attributes to survive Beaver Builder theme overrides
- Seed data uses realistic `firstname.lastname@domain` emails instead of `testuser_XX@example.com`

### Fixed

- Domain filtering now correctly **includes** users from `scaleaq.com`, `moenmarin.no`, `maskon.no`, and `scaleaq.academy` (was inverted — previously excluded them)
- Exclusion patterns now use `user_email NOT LIKE '%pattern%'` instead of `user_login NOT IN (...)`, matching the original Code Snippets logic
- Default date range changed from hardcoded 2025 to all-time, matching original snippet behavior
- Filter button no longer shows as orange pill shape due to theme overrides

## [1.0.1] - 2026-03-08

### Fixed

- Use correct usermeta key `msGraphCompanyName` for company lookup

## [1.0.0] - 2026-03-08

### Added

- Course completion report with donut chart, company bar chart, and group completion table (`[scaleaq_course_report]`)
- User report with per-user completion status (`[scaleaq_user_report]`)
- Date range and company filters on both reports
- CSV export for both reports
- WP-CLI seed command (`wp scaleaq seed`) for local development
- Custom CSS with responsive design
