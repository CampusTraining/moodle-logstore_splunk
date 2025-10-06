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
 * Splunk log store.
 *
 * @package    logstore_splunk
 * @copyright  2016 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_splunk\task;

defined('MOODLE_INTERNAL') || die();

class export_task extends \core\task\scheduled_task {
    private const NOTIFY_COOLDOWN = 3600;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskexport', 'logstore_splunk');
    }

    /**
     * Export logs to Splunk.
     */
    public function execute() {
        global $DB;

        // Check mode.
        $config = get_config('logstore_splunk');
        $mode = isset($config->mode) ? $config->mode : 'realtime';
        if ($mode === 'realtime') {
            $this->clear_failure_state();
            return true;
        }

        // Check Splunk works.
        $splunk = \logstore_splunk\splunk::instance();
        if (!$splunk->is_ready()) {
            $this->notify_failure('notification_splunknotready');
            return false;
        }

        // Safeguard.
        $lockfactory = \core\lock\lock_config::get_lock_factory('logstore_splunk');
        $lock = $lockfactory->get_lock('sync', 5);
        if (!$lock) {
            $this->notify_failure('notification_lockfailed');
            return false;
        }

        // Things may have changed.
        $config = (object)$DB->get_records_menu('config_plugins', array('plugin' => 'logstore_splunk'), '', 'name, value');

        // Grab our last ID.
        $lastid = isset($config->lastentry) ? (int)$config->lastentry : -1;

        // Grab the recordset.
        try {
            $rs = $DB->get_recordset_select('logstore_standard_log', 'id > ?', array($lastid), 'id', '*', 0, 100000);
            foreach ($rs as $row) {
                \logstore_splunk\splunk::log_standardentry($row);

                $lastid = (int)$row->id;
            }
            $rs->close();

            // Flush Splunk.
            $splunk->flush();

            // Update config.
            set_config('lastentry', $lastid, 'logstore_splunk');
            set_config('lastrun', time(), 'logstore_splunk');
            $this->clear_failure_state();
        } catch (\Throwable $throwable) {
            $this->notify_failure('notification_exporterror', ['message' => $throwable->getMessage()]);
            throw $throwable;
        } finally {
            // Unlock.
            $lock->release();
        }

        return true;
    }

    /**
     * Record a failure and alert site administrators.
     *
     * @param string $reasonkey Language string key for the failure reason.
     * @param array $a Data for the string placeholder.
     */
    protected function notify_failure(string $reasonkey, array $a = []): void {
        global $CFG;

        $reason = get_string($reasonkey, 'logstore_splunk', (object)$a);
        $now = time();

        set_config('lastfailuremessage', $reason, 'logstore_splunk');
        set_config('lastfailuretime', $now, 'logstore_splunk');

        $lastnotified = (int)get_config('logstore_splunk', 'lastfailurenotify');
        if ($lastnotified && ($now - $lastnotified) < self::NOTIFY_COOLDOWN) {
            return;
        }

        require_once($CFG->dirroot . '/message/lib.php');

        $admins = get_admins();
        if (empty($admins)) {
            return;
        }

        $sender = \core_user::get_noreply_user();
        $messagecontent = get_string('notificationbody', 'logstore_splunk', (object)['reason' => $reason]);

        foreach ($admins as $admin) {
            $eventdata = new \core\message\message();
            $eventdata->component = 'logstore_splunk';
            $eventdata->name = 'splunkfailure';
            $eventdata->userfrom = $sender;
            $eventdata->userto = $admin;
            $eventdata->subject = get_string('notificationsubject', 'logstore_splunk');
            $eventdata->fullmessage = $messagecontent;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = '';
            $eventdata->smallmessage = $reason;
            $eventdata->notification = 1;

            message_send($eventdata);
        }

        set_config('lastfailurenotify', $now, 'logstore_splunk');
    }

    /**
     * Reset failure tracking indicators after a successful run.
     */
    protected function clear_failure_state(): void {
        $hasmessage = (string)get_config('logstore_splunk', 'lastfailuremessage') !== '';
        $hastime = (int)get_config('logstore_splunk', 'lastfailuretime') !== 0;
        $lastnotify = (int)get_config('logstore_splunk', 'lastfailurenotify');

        if ($hasmessage || $hastime) {
            set_config('lastfailuremessage', '', 'logstore_splunk');
            set_config('lastfailuretime', 0, 'logstore_splunk');
        }

        if ($lastnotify !== 0) {
            set_config('lastfailurenotify', 0, 'logstore_splunk');
        }
    }
}
