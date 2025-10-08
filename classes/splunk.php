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

namespace logstore_splunk;

defined('MOODLE_INTERNAL') || die();

/**
 * Splunk interface.
 */
class splunk
{
    const TRANSPORT_RECEIVER = 'receiver';
    const TRANSPORT_HEC = 'hec';

    private static $instance;
    private static $cachedfilters;

    private $service;
    private $config;
    private $buffer = array();
    private $ready;
    private $transport = self::TRANSPORT_RECEIVER;

    /**
     * Constructor.
     */
    private function __construct() {
        $this->ready = false;
        try {
            if ($this->setup()) {
                $this->ready = true;
            }
        } catch (\Exception $e) { }
    }

    /**
     * Setup the connection.
     */
    private function setup() {
        $this->config = get_config('logstore_splunk');
        if (!isset($this->config->servername) || $this->config->servername === '') {
            return false;
        }

        $this->transport = $this->detect_transport();

        if ($this->transport === self::TRANSPORT_HEC) {
            return $this->setup_hec();
        }

        return $this->setup_receiver();
    }

    /**
     * Singleton.
     */
    public static function instance() {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Flush buffers.
     */
    public function dispose() {
        if (!empty($this->buffer)) {
            $this->flush();
        }
    }

    /**
     * Destructor.
     */
    public function __destruct() {
        $this->dispose();
    }

    /**
     * Are we ready?
     */
    public function is_ready() {
        return $this->ready;
    }

    /**
     * Is Splunk enabled?
     */
    public static function is_enabled() {
        $enabled = get_config('tool_log', 'enabled_stores');
        if (empty($enabled)) {
            return false;
        }

        $enabledstores = array_filter(array_map('trim', explode(',', (string)$enabled)));
        $enabledstores = array_flip($enabledstores);

        return !empty($enabledstores['logstore_splunk']);
    }

    /**
     * Log an item with Splunk.
     * @param $data JSON
     */
    public static function log($data) {
        $splunk = static::instance();
        $splunk->buffer[] = $data;

        if (count($splunk->buffer) > 100) {
            $splunk->flush();
        }
    }

    /**
     * Store a standard log item with Splunk.
     * @param $data
     */
    public static function log_standardentry($data) {
        $data = (array)$data;

        $eventname = isset($data['eventname']) ? $data['eventname'] : null;
        if (!static::should_export_event($eventname)) {
            return;
        }

        $newrow = new \stdClass();
        $newrow->timestamp = date(DATE_ATOM, (int)$data['timecreated']);
        foreach ($data as $k => $v) {
            if ($k == 'other') {
                $tmp = unserialize($v);
                if ($tmp !== false) {
                    $v = $tmp;
                }
            }

            $newrow->$k = $v;
        }

        static::log(json_encode($newrow));
    }

    /**
     * End the buffer.
     */
    public function flush() {
        global $CFG;

        if (empty($this->buffer) || !$this->is_ready()) {
            return;
        }

        if ($this->transport === self::TRANSPORT_HEC) {
            $this->flush_hec();
            return;
        }

        $this->flush_receiver();
    }

    /**
     * Check whether an event should be exported based on the configured filters.
     *
     * @param string|null $eventname Fully-qualified event class name.
     * @return bool
     */
    public static function should_export_event($eventname) {
        if (self::$cachedfilters === null) {
            $config = get_config('logstore_splunk');
            $filters = array();
            if (!empty($config->eventfilters)) {
                $values = preg_split('/[\r\n]+/', (string)$config->eventfilters);
                if ($values) {
                    foreach ($values as $value) {
                        $value = trim($value);
                        if ($value === '') {
                            continue;
                        }
                        $filters[] = ltrim($value, '\\');
                    }
                }
            }

            self::$cachedfilters = $filters;
        }

        if (empty(self::$cachedfilters)) {
            return true;
        }

        if (empty($eventname)) {
            return false;
        }

        $eventname = ltrim($eventname, '\\');

        return in_array($eventname, self::$cachedfilters, true);
    }

    /**
     * Determine configured transport.
     *
     * @return string
     */
    private function detect_transport() {
        if (!isset($this->config->transport) || $this->config->transport === '') {
            return self::TRANSPORT_RECEIVER;
        }

        $transport = $this->config->transport;
        if ($transport !== self::TRANSPORT_HEC) {
            return self::TRANSPORT_RECEIVER;
        }

        return $transport;
    }

    /**
     * Setup the classic receiver transport.
     *
     * @return bool
     */
    private function setup_receiver() {
        require_once(dirname(__FILE__) . '/../lib/splunk/Splunk.php');

        $this->service = new \Splunk_Service(array(
            'host' => $this->config->servername,
            'port' => $this->config->port,
            'username' => $this->config->username,
            'password' => $this->config->password
        ));

        // Login to Splunk.
        $this->service->login();

        return true;
    }

    /**
     * Setup the HTTP Event Collector transport.
     *
     * @return bool
     */
    private function setup_hec() {
        if (empty($this->config->hectoken)) {
            return false;
        }

        if (empty($this->config->hecport)) {
            $this->config->hecport = 8088;
        }

        if (!isset($this->config->hecuseshttps)) {
            $this->config->hecuseshttps = 1;
        }

        if (empty($this->config->hecendpoint)) {
            $this->config->hecendpoint = '/services/collector/event';
        }

        return true;
    }

    /**
     * Flush buffer via the Splunk receiver API.
     */
    private function flush_receiver() {
        $reciever = $this->service->getReceiver();
        $reciever->submit(implode("\n", $this->buffer), array(
            'host' => $this->config->hostname,
            'index' => $this->config->indexname,
            'source' => $this->config->source,
            'sourcetype' => 'json'
        ));

        $this->buffer = array();
    }

    /**
     * Flush buffer via the HTTP Event Collector.
     *
     * @throws \moodle_exception If Splunk reports an error.
     */
    private function flush_hec() {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $curl->setHeader(array(
            'Authorization: Splunk ' . trim($this->config->hectoken),
            'Content-Type: application/json'
        ));

        $records = array();
        foreach ($this->buffer as $entry) {
            $decoded = json_decode($entry, true);
            $payload = array(
                'sourcetype' => 'json',
                'source' => $this->config->source,
                'host' => $this->config->hostname,
                'index' => $this->config->indexname
            );

            if (is_array($decoded)) {
                $payload['event'] = $decoded;

                if (isset($decoded['timecreated'])) {
                    $payload['time'] = (int)$decoded['timecreated'];
                } else if (isset($decoded['timestamp'])) {
                    $time = strtotime($decoded['timestamp']);
                    if ($time) {
                        $payload['time'] = $time;
                    }
                }
            } else {
                $payload['event'] = $entry;
            }

            $records[] = json_encode($payload);
        }

        $endpoint = trim((string)$this->config->hecendpoint);
        if ($endpoint === '') {
            $endpoint = '/services/collector/event';
        }
        if ($endpoint[0] !== '/') {
            $endpoint = '/' . $endpoint;
        }

        $scheme = !empty($this->config->hecuseshttps) ? 'https://' : 'http://';
        $url = $scheme . $this->config->servername . ':' . $this->config->hecport . $endpoint;

        $response = $curl->post($url, implode("\n", $records));
        $info = $curl->get_info();
        $httpcode = isset($info['http_code']) ? (int)$info['http_code'] : 0;

        if ($httpcode < 200 || $httpcode >= 300) {
            throw new \moodle_exception('splunkhecerror', 'logstore_splunk', '', (object)array(
                'code' => $httpcode,
                'message' => $response
            ));
        }

        $decodedresponse = json_decode($response);
        if ($decodedresponse !== null && isset($decodedresponse->code) && (int)$decodedresponse->code !== 0) {
            $message = isset($decodedresponse->text) ? $decodedresponse->text : $response;
            throw new \moodle_exception('splunkhecerror', 'logstore_splunk', '', (object)array(
                'code' => $decodedresponse->code,
                'message' => $message
            ));
        }

        $this->buffer = array();
    }
}
