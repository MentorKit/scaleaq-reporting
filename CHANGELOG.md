# Changelog

All notable changes to the ScaleAQ Reporting plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.7.2] - 2026-09-26

### Added

- Email domains `pmh.no` and `probotic.no` in `get_allowed_email_domains()` (eligible users / scope line)

## [1.7.1] - 2026-09-26

## [1.7.1] - 2026-09-26

### Changed

- **Started / In progress** now count any `learndash_user_activity` row with `course_id` in the language-variant IDs (lesson/topic/quiz/access/course), aligning with ProPanel before filters
- Scope line under stat cards is built from `get_allowed_email_domains()` (same source as `get_base_where()`)
- Progress donut and Company Completion Rates sit side-by-side (1/3 + 2/3); donut legend is a vertical list

## [1.7.0] - 2026-09-26

### Added

- Funnel metrics: **Enrolled**, **Not started**, **In progress**, **Completed**, plus Completion rate and Completion rate (started)
- Shared metric definitions (`get_metric_definitions()`) used in stat cards, company/group tables, and CSV
- Help text under each stat card; scope line under the cards
- Report subtitle with course title and language codes (NO / EN / ES)
- Group rows for **Maskon**, **Probotic**, and **PMH** (plus ScaleAQ Group, Moen Marin AS, Other)

### Changed

- Renamed **Assigned** → **Enrolled** (LearnDash / ProPanel terminology)
- Removed Total users and Not completed stat cards (replaced by Not started + In progress)
- Donut chart: three segments (Completed / In progress / Not started)
- Company chart replaced by a rate-sorted table: Enrolled | Started | Completed | Rate
- Empty company names display as `(no company)`
- CSV columns: ID, Email, First name, Last name, Company, Group, Enrolled, Started, Completed, Status, Language, Completed date

## [1.6.0] - 2026-09-26

## [1.6.0] - 2026-09-26

### Added

- **Assigned** and **Started** metrics on Course Completion (and User Report / CSV when a category is selected)
- Assigned = eligible users with LearnDash group access, direct `course_{id}_access_from`, or an open course (SQL, one query per category)
- Started = Assigned users with any `learndash_user_activity` course row for the selected language IDs
- Stat cards: Total users | Assigned | Started | Completed | Not completed | Completion rate
- Line for **Completed (no longer assigned)** when users finished but no longer have access
- CSV columns: Assigned, Started, Language (course + user reports)

### Changed

- Completion rate and Not completed use **Assigned** as denominator (`Completed / Assigned`, `Assigned − Completed`)
- Company and Group tables: Total column is Assigned count for that company/group
- Not completed drill-down lists only assigned users
- `get_group_label()` maps company names containing **SCALE AQUACULTURE** to ScaleAQ Group

## [1.5.0] - 2026-09-25

### Added

- Polylang-aware course grouping: each category uses one canonical (Norwegian) course ID; completions count across all published language variants of that course
- New **AI** category (`cr_cat=ai` / `ur_cat=ai`) for course *Grunnkurs AI og Copilot* (55110), removed from IT
- **Language** column in drill-down tables, User Report (when a category is selected), and both CSV exports — shows the language of the user’s latest completion (NO/EN/ES)
- `get_course_language_ids()` and hardcoded translation fallbacks (including IT Spanish 52985) when Polylang is unavailable

### Changed

- Course filter dropdown shows one entry per logical course (Norwegian title), not per language version
- Category course maps: HSE `[47052]`, CoC `[47053]`, IT `[50348]`, AI `[55110]`
- Legacy `cr_course` / `ur_course` URLs with an old language-specific post ID (e.g. `46681`) still resolve to the correct course

## [1.4.0] - 2026-08-24

### Added

- Course filter dropdown on Course Completion and User Report — pick a single course within the selected category (`cr_course` / `ur_course`)
- Default remains "All courses in category" so existing URLs and behaviour stay unchanged

## [1.3.1] - 2026-08-24

### Changed

- Added course ID `55110` (*Grunnkurs AI og Copilot*) to the IT category (`cr_cat=it` / `ur_cat=it`)

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
