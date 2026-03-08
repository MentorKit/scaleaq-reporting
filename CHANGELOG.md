# Changelog

All notable changes to the ScaleAQ Reporting plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-03-08

### Fixed

- Domain filtering now correctly **includes** users from `scaleaq.com`, `moenmarin.no`, `maskon.no`, and `scaleaq.academy` (was inverted — previously excluded them)
- Exclusion patterns now use `user_email NOT LIKE '%pattern%'` instead of `user_login NOT IN (...)`, matching the original Code Snippets logic
- Default date range changed from hardcoded 2025 to all-time, matching original snippet behavior
- Header subtitle shows "All time" instead of blank dashes when no date filter is set

### Changed

- Removed conflicting shortcode aliases (`my_course_report_form`, `simple_user_report`) to allow side-by-side testing with live Code Snippets
- Seed data uses realistic `firstname.lastname@domain` emails instead of `testuser_XX@example.com`

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
