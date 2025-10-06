# Repository Guidelines

## Project Structure & Module Organization
The plugin follows Moodle's logstore layout. Event ingestion is handled in `classes/log/store.php`, now delegating to `splunk::should_export_event()` so respect the filter list in settings. Background sync resides in `classes/task/export_task.php`, which manages retry state and user notifications; related message provider metadata lives in `db/messages.php`. Health checks for monitoring stay under `classes/nagios/`. Splunk's PHP SDK remains vendored in `lib/splunk/` (declared in `thirdpartylibs.xml`) and must not be modified. UI strings sit in `lang/en/logstore_splunk.php`, and administrator controls are defined in `settings.php`.

## Build, Test, and Development Commands
Work inside a Moodle checkout with the plugin at `moodle/logstore/splunk`. Useful commands:
- `moodle-plugin-ci install --moodle /path/to/moodle` — bootstrap a disposable Moodle site for plugin testing.
- `moodle-plugin-ci phpunit --testsuite logstore_splunk` — run PHPUnit coverage, including new filtering paths.
- `moodle-plugin-ci behat --profile default --tags=@logstore_splunk` — execute Behat features tied to admin settings.
After schema or string changes, run `php admin/cli/upgrade.php --non-interactive` to install updates like `db/messages.php`.

## Coding Style & Naming Conventions
Follow Moodle PHP standards: 4-space indentation, `PSR-2` braces, and Moodle's Yoda comparisons when required. Ensure namespaces mirror paths (`classes/task/export_task.php` → `logstore_splunk\task\export_task`). Language string identifiers remain lowercase with underscores; new message keys (`messageprovider:splunkfailure`) should match the capability expectation. Run `moodle-plugin-ci lint` before pushing to satisfy phpcs and lint rules.

## Testing Guidelines
Place new PHPUnit cases beneath `tests/` mirroring namespaces (e.g. `tests/splunk/should_export_event_test.php`). Cover both real-time dispatch (`log_standardentry`) and cron execution, asserting cache behaviour for `eventfilters` and failure-state resets. For Behat, prefix feature filenames with `logstore_splunk_` and verify admin messaging flows. Maintain fixtures for Splunk API responses using Moodle's `advanced_testcase` helpers.

## Commit & Pull Request Guidelines
Use concise, imperative commit subjects ("Add event filter support"). When bumping release metadata (`version.php`, `$plugin->release`), explain compatibility. Pull requests should document configuration changes (event filters textarea, notification defaults), include `moodle-plugin-ci` output, and attach UI screenshots if admin pages changed. Request review from maintainers familiar with Moodle logging and Splunk integrations.

## Configuration Tips
`settings.php` now exposes an `Event filters` textarea; provide fully-qualified event class names to limit exports, otherwise leave blank. Failure notifications are surfaced via the `splunkfailure` message provider, so confirm admin messaging preferences after deployment. Default hostnames and rate controls remain in settings—prefer configuration over code edits, and keep secrets in `config.php` not source control.
