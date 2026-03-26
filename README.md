# ScaleAQ Reporting

WordPress plugin for ScaleAQ Academy (LearnDash LMS) — course completion and user reports.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- LearnDash LMS (or seeded data for local dev)

## Shortcodes

| Shortcode | Description |
|---|---|
| `[scaleaq_course_report]` | Course completion report with charts, filters, and CSV export |
| `[scaleaq_user_report]` | User list with per-user completion status and CSV export |

### Filters (query parameters)

**Course report:** `cr_cat`, `cr_period`, `cr_to`, `cr_company[]`, `cr_export`
**User report:** `ur_cat`, `ur_period`, `ur_to`, `ur_company[]`, `ur_export`

The company filter accepts multiple values: `?cr_company[]=ScaleAQ+AS&cr_company[]=Moen+Marin+AS`. Single-value strings (`?cr_company=ScaleAQ+AS`) are also supported for backward compatibility.

## Course categories

| Key | Label | Course IDs |
|---|---|---|
| `hse` | HSE | 46681, 47052, 47386 |
| `coc` | CoC | 47232, 46085, 47053 |
| `it` | IT | 50346, 50348 |

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
│   └── css/reports.css          # Report styles
├── CHANGELOG.md
└── composer.json
```
