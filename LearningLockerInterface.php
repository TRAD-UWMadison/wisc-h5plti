<?php

/**
 * Author: Ian McNamara <ian.mcnamara@wisc.edu>
 *         Teaching and Research Application Development
 * Copyright 2018 Board of Regents of the University of Wisconsin System
 */
class LearningLockerInterface {

    const VERB_COMPLETED = "http%3A%2F%2Fadlnet.gov%2Fexpapi%2Fverbs%2Fcompleted";
    const VERB_ANSWERED = "http%3A%2F%2Fadlnet.gov%2Fexpapi%2Fverbs%2Fanswered";

    const ERROR_ENDPOINT_BASE = 'LearningLockerInterface::ERROR_ENDPOINT_BASE';
    const ERROR_CURL_XAPI = 'LearningLockerInterface::ERROR_CURL_XAPI';
    const ERROR_DECODE_XAPI_RESPONSE = 'LearningLockerInterface::ERROR_DECODE_XAPI_RESPONSE';

    const STATEMENT_LIMIT_PER_REQUEST = 200;

    private static $activity_url_prefixes_by_blog_id = array();

    private static function get_since_arg($since = null) {
        return is_null($since) ? '' : '&since=' . $since->format('Y-m-d') . 'T00:00:00.0000000Z';
    }
    private static function get_until_arg($until = null) {
        return is_null($until) ? '' : '&until=' . $until->format('Y-m-d') . 'T23:59:59.9999999Z';
    }

    /**
     * @param string $verb
     * @param integer $id
     * @param string $since_arg
     * @param string $until_arg
     * @throws ErrorException Bubbles EE up from self::get_request_url_format
     * @return string
     */
    private static function get_request_url($verb, $id, $since_arg, $until_arg) {
        return sprintf(self::get_request_url_format(), $verb, self::get_activity_url_prefix(), $id, $since_arg, $until_arg);
    }

    private static $request_url_format;
    /**
     * @return string
     * @throws ErrorException Bubbles up from WiscH5PLTI::get_learning_locker_settings
     */
    private static function get_request_url_format() {
        if (empty(self::$request_url_format)) {
            $settings = WiscH5PLTI::get_learning_locker_settings();
            self::$request_url_format = $settings["endpoint_url"] . 'statements?' .
                'verb=%s' .
                '&activity=%s%d' .
                '&limit=' . self::STATEMENT_LIMIT_PER_REQUEST . '&format=exact' .
                // since/until date query params
                '%s%s';
        }
        return self::$request_url_format;
    }

    private static function get_activity_url_prefix() {
        $blog_id = get_current_blog_id();
        if ( ! isset( self::$activity_url_prefixes_by_blog_id[$blog_id] ) ) {
            self::$activity_url_prefixes_by_blog_id[$blog_id] = urlencode(get_site_url() . "/wp-admin/admin-ajax.php?action=h5p_embed&id=");
        }
        return self::$activity_url_prefixes_by_blog_id[$blog_id];
    }

    /**
     * @param $blog_id
     * @param $h5p_ids
     * @param DateTime|null $since
     * @param DateTime|null $until
     * @return array|void
     * @throws ErrorException
     *      Exception may be bubbled up from WiscH5PLTI::get_learning_locker_settings.
     *      If a 'base' endpoint URL cannot be constructed from the endpoint URL.
     *      Bubbles up from self::get_request_url
     *      Bubbles up from self::get_h5p_statements_helper
     */
    public static function get_h5p_statements($blog_id, $h5p_ids, $since = null, $until = null) {

        switch_to_blog($blog_id);
        $settings = WiscH5PLTI::get_learning_locker_settings($blog_id); // can throw EE
        $auth_user = $settings["username"];
        $auth_password = $settings["password"];
        $endpoint_url = $settings["endpoint_url"];
        $endpoint_data_pos = stripos($endpoint_url, '/data/xAPI');
        if ($endpoint_data_pos === FALSE) {
            throw new ErrorException(self::ERROR_ENDPOINT_BASE);
        }
        $more_base_url = substr($endpoint_url, 0, $endpoint_data_pos);
        $auth = base64_encode($auth_user . ":" . $auth_password);

        $curl_options = array(
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . $auth,
                'x-experience-api-version: 1.0.1')
        );

        $since_arg = self::get_since_arg($since);
        $until_arg = self::get_until_arg($until);
        $all_statements = array();
        foreach($h5p_ids as $h5p_id) {
            $request_url = self::get_request_url(self::VERB_COMPLETED, $h5p_id, $since_arg, $until_arg);
            WiscH5PLTI::write_log("sync_grades_for_post | completed: request_url='$request_url'");
            self::get_h5p_statements_helper($request_url, $more_base_url, $curl_options, $all_statements);
            $request_url = self::get_request_url(self::VERB_ANSWERED, $h5p_id, $since_arg, $until_arg);
            WiscH5PLTI::write_log("sync_grades_for_post | answered: request_url='$request_url'");
            self::get_h5p_statements_helper($request_url, $more_base_url, $curl_options, $all_statements);
        }
        return $all_statements;
    }

    /**
     * @param $request_url
     * @param $more_base_url
     * @param $curl_options
     * @param $statements
     * @throws ErrorException
     */
    private static function get_h5p_statements_helper($request_url, $more_base_url, $curl_options, &$statements) {
        
        // Initialize
        $ch = curl_init($request_url);
        if ($ch === FALSE) {
            throw new ErrorException(self::ERROR_CURL_XAPI . " | Failed to initialize on url '$request_url'");
        }

        // Set the options!
        $result = curl_setopt_array($ch, $curl_options);
        if ($result) {
            // Never output the result - instead, return the data
            $result = curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }
        if ($result === FALSE) {
            throw new ErrorException(self::ERROR_CURL_XAPI . " | Failed to set one or more curl options.");
        }

        // Run!
        $response = curl_exec($ch);
        if ($response === FALSE) {
            $error_number = curl_errno($ch);
            throw new ErrorException(self::ERROR_CURL_XAPI . " | curl_exec failed; error number: '$error_number'");
        }

        // Success, it would seem.
        curl_close($ch);

        $response_object = json_decode($response, true);
        if (is_null($response_object)) {
            throw new ErrorException(self::ERROR_DECODE_XAPI_RESPONSE);
        }
        if (key_exists('statements', $response_object) && count($response_object['statements']) > 0) {
            $statements = array_merge($statements, $response_object['statements']);
            if (key_exists('more', $response_object) && !empty($response_object['more'])) {
                $more_url = $more_base_url . $response_object['more'];
                self::get_h5p_statements_helper($more_url, $more_base_url, $curl_options, $statements);
            }
        }
    }

    
}