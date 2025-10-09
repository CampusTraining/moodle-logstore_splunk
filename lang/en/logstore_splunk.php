<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Splunk log store lang.
 *
 * @package    logstore_splunk
 * @copyright  2016 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Splunk log';
$string['pluginname_desc'] = 'A log plugin storing log entries in Splunk.';

$string['servername'] = 'Splunk HEC host';
$string['indexname'] = 'Index name';
$string['mode'] = 'Export mode';
$string['realtime'] = 'Realtime';
$string['background'] = 'Background';
$string['hostname'] = 'Hostname of sender';
$string['source'] = 'Source name';
$string['eventfilters'] = 'Event filters';
$string['eventfilters_desc'] = 'Optional newline-separated list of event class names (for example \\core\\event\\user_loggedin). Only matching events are exported to Splunk. Leave empty to export all events.';
$string['hecport'] = 'HEC port';
$string['hecport_desc'] = 'Port for the Splunk HTTP Event Collector endpoint.';
$string['hecuseshttps'] = 'Use HTTPS for HEC';
$string['hecuseshttps_desc'] = 'Enable if the HTTP Event Collector is exposed over HTTPS.';
$string['hecendpoint'] = 'HEC endpoint path';
$string['hecendpoint_desc'] = 'Relative path to the HTTP Event Collector endpoint (for example /services/collector/event).';
$string['hectoken'] = 'HEC token';
$string['hectoken_desc'] = 'Authentication token generated for the HTTP Event Collector input.';

$string['taskexport'] = 'Export to Splunk';

$string['reporttitle'] = 'Splunk health';
$string['repstatus'] = 'Replication Status';
$string['never'] = 'Never';
$string['lastran'] = 'Last ran';
$string['progress'] = 'Progress';
$string['lastfailure'] = 'Last failure';
$string['lastfailuremessage'] = 'Failure message';
$string['lastfailuretime'] = 'Failure time';
$string['messageprovider:splunkfailure'] = 'Splunk log store failures';
$string['notificationsubject'] = 'Splunk log store export failed';
$string['notificationbody'] = 'The Splunk log store background export failed: {$a->reason}';
$string['notification_splunknotready'] = 'Splunk service is not ready. Check the connection settings.';
$string['notification_lockfailed'] = 'Splunk export skipped because the task lock could not be obtained.';
$string['notification_exporterror'] = 'Unexpected error while exporting to Splunk: {$a->message}';
$string['splunkhecerror'] = 'Splunk HEC request failed (code {$a->code}): {$a->message}';
$string['privacy:metadata'] = 'The Splunk log store plugin does not store personal data within Moodle.';
