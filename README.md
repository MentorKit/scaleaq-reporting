# ScaleAQ Reporting

WordPress plugin for ScaleAQ Academy (LearnDash LMS) — course completion and user reports.

**Current version:** 1.7.1

## Contributors

- [Martin Morfjord](https://github.com/morfjord) ([@morfjord](https://github.com/morfjord)) — `morfjord@gmail.com`

## Requirements

- WordPress 6.0+
- PHP 8.0+
- LearnDash LMS (or seeded data for local dev)

## Shortcodes

| Shortcode | Description |
|---|---|
| `[scaleaq_course_report]` | Course completion report with charts, filters, and CSV export |
| `[scaleaq_user_report]` | User list with per-user completion status and CSV export |

### Filters (UI)

Both reports share the same filter bar layout:

| Filter | Purpose |
|---|---|
| **Category** | Course group: HSE, CoC, IT, or AI (User Report also allows “All Courses”) |
| **Course** | Optional single logical course within the selected category (one Norwegian title per Polylang group). Default: all courses in that category |
| **Company** | Multi-select company filter |
| **Time Period** | All time, cutoff presets, or custom cutoff date |

Changing **Category** clears the course selection and refreshes the course list (titles come from LearnDash course posts).

On **User Report**, the Course dropdown is disabled until a category is selected.

### Filters (query parameters)

**Course report:** `cr_cat`, `cr_course`, `cr_period`, `cr_to`, `cr_company[]`, `cr_export`  
**User report:** `ur_cat`, `ur_course`, `ur_period`, `ur_to`, `ur_company[]`, `ur_export`

| Parameter | Description |
|---|---|
| `cr_cat` / `ur_cat` | Category key (`hse`, `coc`, `it`, `ai`). User report: empty = all courses / no completion column |
| `cr_course` / `ur_course` | Canonical (Norwegian) LearnDash course post ID for the selected category. Legacy language-specific IDs in old URLs are still accepted. Empty (default) = all courses in that category |
| `cr_period` / `ur_period` | `all`, `2025`, `2024`, or `custom` |
| `cr_to` / `ur_to` | Cutoff date `YYYY-MM-DD` (used when period is `custom`) |
| `cr_company[]` / `ur_company[]` | One or more company names |
| `cr_export` / `ur_export` | Set to `1` to download CSV with the current filters |

The company filter accepts multiple values: `?cr_company[]=ScaleAQ+AS&cr_company[]=Moen+Marin+AS`. Single-value strings (`?cr_company=ScaleAQ+AS`) are also supported for backward compatibility.

Example — IT category, single course:

```
?cr_cat=it&cr_course=50348
```

## Course categories

Each category lists one **canonical** (Norwegian) LearnDash post ID. Reports treat a user as **Completed** when they have finished **at least one** published language variant of **any** course in the selection (Polylang translations are merged via `get_course_language_ids()`).

| Key | Label | Canonical course ID | Language variants (fallback IDs) |
|---|---|---|---|
| `hse` | HSE | 47052 | 46681 (EN), 47052 (NO), 47386 (ES) |
| `coc` | CoC | 47053 | 46085 (EN), 47053 (NO), 47232 (ES) |
| `it` | IT | 50348 | 50346 (EN), 50348 (NO), 52985 (ES) |
| `ai` | AI | 55110 | 55110 |

Categories and canonical IDs are defined in `includes/class-report-base.php` (`get_course_ids_map()` / `get_category_labels()`). Translation groups use Polylang when available, with `get_course_translation_fallbacks()` as backup.

## Metrics (Course Completion)

Labels and help text live in `get_metric_definitions()`.

| Metric | Meaning |
|---|---|
| **Enrolled** | Eligible users with access to ≥1 language variant (LearnDash group or direct enrollment) |
| **Not started** | Enrolled, no activity with `course_id` = language variant |
| **In progress** | Enrolled with any LearnDash activity for the course (`course_id`), not completed |
| **Completed** | Enrolled and finished ≥1 language variant (once per person) |
| **Completion rate** | Completed ÷ Enrolled |
| **Completion rate (started)** | Completed ÷ Started (Started = In progress + Completed) |
| **Completed (no longer enrolled)** | Finished but no longer enrolled (shown separately) |

Scope line under the cards: employees matching `get_base_where` (subscribers with ScaleAQ / Moen Marin / Maskon emails; test and service accounts excluded).

## Domain filtering

Only users with emails matching these domains are included:

- `scaleaq.com`
- `moenmarin.no`
- `maskon.no`
- `scaleaq.academy`

Emails containing `demo`, `revisor`, `test`, `dummy`, `admin`, `support`, `spare.equipment`, `logistics`, `bank`, `accounts`, `seleccion`, or `developers` are excluded.

## Local development

Seed test data (50 users + LearnDash activity):

```bash
wp scaleaq seed
```

Reset and re-seed:

```bash
wp scaleaq seed --reset
```

## File structure

```
scaleaq-reporting/
├── scaleaq-reporting.php        # Plugin bootstrap
├── includes/
│   ├── class-report-base.php    # Shared query logic and helpers
│   ├── class-course-report.php  # Course completion report
│   ├── class-user-report.php    # User report
│   └── class-cli-seed.php       # WP-CLI seed command
├── assets/
│   ├── css/reports.css          # Report styles
│   └── js/reports.js            # Multiselect + drill-down UI
├── CHANGELOG.md
└── composer.json
```
