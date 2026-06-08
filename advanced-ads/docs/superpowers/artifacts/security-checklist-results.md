# Security checklist results (Wave C — sample-based)

**Method:** Repository-wide ripgrep for high-signal patterns, then manual review of **highest-churn** PHP files from `php-file-metrics.tsv` and concentrated AJAX/REST modules.

**Repo snapshot:** See parent health report for commit SHA and date.

## Summary

| Item                                | Scope                                                          | Result           | Notes                                                                                                                                                                                                                                                                                                                |
| ----------------------------------- | -------------------------------------------------------------- | ---------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| AJAX: nonce + capability            | `includes/admin/class-ajax.php` (32 `wp_ajax_*` registrations) | **Pass / mixed** | Many handlers use `wp_verify_nonce` + `Conditional::user_can` (e.g. `subscribe_to_newsletter`). **Follow-up:** audit every handler; several `wp_ajax_nopriv_*` endpoints exist (`advads_ad_select`, `advads-ad-health-notice-push`) — verify intentional public surface and rate/abuse controls.                     |
| `$_REQUEST` usage                   | Plugin-owned paths (excludes Action Scheduler list table)      | **Review**       | Matches in `includes/admin/class-ajax.php`, `includes/admin/class-list-filters.php`, `includes/admin/class-edd-updater.php`. Each needs case-by-case sanitization and authorization.                                                                                                                                 |
| REST routes                         | `includes/rest/*.php`, modules                                 | **Review**       | `register_rest_route` present in multiple classes; confirm `permission_callback` on each route (not audited line-by-line in this pass).                                                                                                                                                                              |
| Dynamic SQL                         | Hot files                                                      | **Review**       | Prioritize `$wpdb` usage in `includes/admin/class-ajax.php`, repositories, importers; confirm `$wpdb->prepare` on variable fragments.                                                                                                                                                                                |
| Output escaping                     | Views / admin pages                                            | **Review**       | Systematic pass deferred; spot-check high-churn admin classes when touching UI.                                                                                                                                                                                                                                      |
| `unserialize` / `maybe_unserialize` | First-party code                                               | **Review**       | `includes/utilities/class-wordpress.php::maybe_unserialize` uses `allowed_classes => false` (good). `includes/importers/class-ad-inserter.php` uses `unserialize` on decoded data (flagged phpcs) — ensure input is trusted/truncated. Third-party (`packages/woocommerce/action-scheduler`) excluded from sign-off. |

## `unserialize` / `maybe_unserialize` (first-party highlights)

| File                                                      | Note                                                                                           |
| --------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `includes/utilities/class-wordpress.php`                  | Wrapper restricts allowed classes.                                                             |
| `includes/importers/class-plugin-exporter.php`            | `maybe_unserialize` on post meta — typical import path; keep capability checks on importer UI. |
| `includes/importers/class-ad-inserter.php`                | `unserialize( base64_decode(...) )` — **high attention** on call path and caller capability.   |
| `includes/importers/class-xml-importer.php`               | Uses `WordPress::maybe_unserialize` for options/meta.                                          |
| `includes/admin/class-edd-updater.php`                    | `maybe_unserialize` on remote API body fields — depends on SSL/signature of updater.           |
| `modules/gadsense/includes/class-adsense-report-data.php` | Custom unserialize helpers — review when changing Adsense module.                              |

## Next pass (recommended)

1. Enumerate every `register_rest_route` and record `permission_callback`.
2. Walk `includes/admin/class-ajax.php` method-by-method for nopriv handlers.
3. Grep `$wpdb->query` / `$wpdb->get_*` without `prepare` in `includes/` and `admin/`.
