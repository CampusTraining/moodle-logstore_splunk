# Splunk log store

A Moodle logstore plugin that forwards platform events to Splunk in real time or via a buffered cron export. Version `v2.3.0 (Build: 2025100603)` enforces HTTP Event Collector delivery alongside event filtering and administrator notifications for delivery issues.

## Features
- Real-time dispatch of Moodle events when the logstore is enabled.
- Scheduled export task with retry logic and failure-state tracking.
- Optional event filters so only selected fully-qualified event class names are forwarded.
- Health check endpoint for external monitoring and Nagios integration.
- Administrator notifications through the `splunkfailure` message provider when exports fail.
- HTTP Event Collector ingestion with newline-delimited JSON payloads.

## Installation
1. Drop this repository into `moodle/logstore/splunk` within your Moodle checkout.
2. Run `php admin/cli/upgrade.php --non-interactive` to install database tables and the message provider defined in `db/messages.php`.
3. Visit *Site administration ▸ Plugins ▸ Logging ▸ Manage log stores* and enable **Splunk log store**.

## Configuration
Key settings live in `Site administration ▸ Plugins ▸ Logging ▸ Splunk log store`:
- **Mode**: Choose realtime, background, or both, depending on your Splunk throughput.
- **HEC connection**: Configure the HEC host, port, HTTPS toggle, endpoint path, and token provided by Splunk.
- **Hostname/Source**: Identify the origin inside Splunk indexes.
- **Event filters**: Provide newline-separated event class names (e.g. `\core\event\user_loggedin`) to restrict exports; leave empty to send all events.
- **Failure notifications**: Configure message preferences for `Splunk log store failures` so admins receive alerts.

The plugin uses Moodle's built-in `curl` helper to talk to Splunk's HTTP Event Collector; no additional SDKs are required.

## Development & Testing
- `moodle-plugin-ci install --moodle /path/to/moodle` — bootstrap a disposable Moodle site for plugin testing.
- `moodle-plugin-ci phpunit --testsuite logstore_splunk` — run PHPUnit coverage, including event filtering and buffer handling.
- `moodle-plugin-ci behat --profile default --tags=@logstore_splunk` — execute Behat features for admin workflows.

For manual verification, trigger the scheduled task with `php admin/cli/scheduled_task.php --execute="\logstore_splunk\task\export_task"` and review Splunk responses in Moodle logs.
