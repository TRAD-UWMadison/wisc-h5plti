<?php

/**
 * Author: Ian McNamara <ian.mcnamara@wisc.edu>
 *         Teaching and Research Application Development
 * Copyright 2018 Board of Regents of the University of Wisconsin System
 */
class LearningLockerInterface {

    const VERB_COMPLETED = "http%3A%2F%2Fadlnet.gov%2Fexpapi%2Fverbs%2Fcompleted";
    const VERB_ANSWERED = "http%3A%2F%2Fadlnet.gov%2Fexpapi%2Fverbs%2Fanswered";

    const ERROR_ENDPOINT_BASE = 'ERROR_ENDPOINT_BASE';

    const STATEMENT_LIMIT_PER_REQUEST = 200;

    //    public $latest_http_responsecode;
//    public $latest_http_contenttype;

    private static function get_since_arg($since = null) {
        return is_null($since) ? '' : '&since=' . $since->format('Y-m-d') . 'T00:00:00.0000000Z';
    }
    private static function get_until_arg($until = null) {
        return is_null($until) ? '' : '&until=' . $until->format('Y-m-d') . 'T00:00:00.0000000Z';
    }

    /**
     * @param string $verb
     * @param integer $id
     * @param string $since_arg
     * @param string $until_arg
     * @throws ErrorException
     * @return string
     */
    private static function get_request_url($verb, $id, $since_arg, $until_arg) {
        try {
            return sprintf(self::get_request_url_format(), $verb, self::get_activity_url_prefix(), $id, $since_arg, $until_arg);
        } catch (ErrorException $e) {
            throw $e;
        }
    }

    private static $request_url_format;
    /**
     * @return string
     * @throws ErrorException
     */
    private static function get_request_url_format() {
        if (empty($request_url_format)) {
            $settings = WiscH5PLTI::get_learning_locker_settings(); // todo: catch error
            if (!is_array($settings) || !key_exists('endpoint_url', $settings) || empty($settings['endpoint_url'])) {
                throw new ErrorException(WiscH5PLTI::ERROR_NOT_CONFIGURED);
            }
            $request_url_format = $settings["endpoint_url"] . 'statements?' .
                'verb=%s' .
                '&activity=%s%d' .
                '&limit=' . self::STATEMENT_LIMIT_PER_REQUEST . '&format=exact' .
                // since/until date query params
                '%s%s';
        }
        return $request_url_format;
    }

    private static $activity_url_prefix;
    private static function get_activity_url_prefix() {
        if (empty($activity_url_prefix)) {
            $activity_url_prefix = urlencode(get_site_url() . "/wp-admin/admin-ajax.php?action=h5p_embed&id=");
        }
        return $activity_url_prefix;
    }
    
    /**
     * @param int $blog_id
     * @param string $verb
     * @param array $ids
     * @param null|DateTime $since
     * @param null|DateTime $until
     * @throws ErrorException
     */
    public static function get_h5p_info($blog_id, $ids, $since = null, $until = null) {

        switch_to_blog($blog_id);
        try {
            $settings = WiscH5PLTI::get_learning_locker_settings($blog_id);
        } catch (ErrorException $e) {
            // todo, react.
            return;
        }
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
        try {
            foreach($ids as $id) {
                $request_url = self::get_request_url(self::VERB_COMPLETED, $id, $since_arg, $until_arg);
                self::get_h5p_info_helper($request_url, $more_base_url, $curl_options, $all_statements);
                $request_url = self::get_request_url(self::VERB_ANSWERED, $id, $since_arg, $until_arg);
                self::get_h5p_info_helper($request_url, $more_base_url, $curl_options, $all_statements);
            }    
        } catch (ErrorException $e) {
            // todo: react somehow
        }
        
        
        

        // todo: switch back to blog?

    }
    
    private static function get_h5p_info_helper($request_url, $more_base_url, $curl_options, &$statements) {
        $response = self::curl_request($request_url, $curl_options);
        if ($response === FALSE) {
            // todo: error reporting
        }
        $response_object = json_decode($response);
        if (is_null($response_object)) {
            // todo: error
        }
        if (property_exists($response_object, 'statements') && count($response_object->statements) > 0) {
            $statements = array_merge($statements, $response_object->statements);
            if (property_exists($response_object, 'more') && !empty($response_object->more)) {
                $more_url = $more_base_url . $response_object->more;
                self::get_h5p_info_helper($more_url, $more_base_url, $curl_options, $statements);
            }
        }
    }



    private static function curl_request($url, $curl_options) {

        // Initialize
        $ch = curl_init($url);
        if ($ch === FALSE) {
            $curl_error_number = curl_errno($ch);
//            $this->logger->error(__FUNCTION__ . " | Failed to initialize on url: '$url', curl_errno: '$curl_error_number'");
            return FALSE;
        }

        // Set the options!
        $result = curl_setopt_array($ch, $curl_options);
        if ($result) {
            // Never output the result - instead, return the data
            $result = curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }
        if ($result === FALSE) {
//            $this->logger->error(__FUNCTION__ . " | Failed to set one or more curl options.");
            return FALSE;
        }

        // Run!
        $data = curl_exec($ch);
        if ($data === FALSE) {
            $error_number = curl_errno($ch);
//            $this->logger->error(__FUNCTION__ . " | curl_exec failed; error number: '$error_number'");
            return FALSE;
        }

        // Record some info to public vars
//        $this->latest_http_responsecode	= curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        $this->latest_http_contenttype 	= curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        // Success, it would seem.
        curl_close($ch);
        return $data;
    }
    
    private static function process_xapi_statements($data) {
        $s=json_decode(stripslashes($data),TRUE);
        $blogid = $s['blog'];
        $postid = $s['post'];
        $gradingScheme = $s['grading'];
        $userData = array();
        $statements = $s['statements'];

        $questions = array();
        $maxTotalGrade = 0;

        foreach ($statements as $statement){
            // Abort if the statement doesn't contain results.
            if (!key_exists('result', $statement)) {
                continue;
            }
            $info = new stdClass();
            $info->timestamp = $statement['timestamp'];
            $info->score = $statement['result']['score']['scaled'];
            $info->maxScore = $statement['result']['score']['max'];
            $info->rawScore = $statement['result']['score']['raw'];
            $info->question = $statement['object']['definition']['name']['en-US'];
            if ($statement['result']['score']['max'] != "0") {
                if (is_null($info->score)){
                    $info->score = floatval($statement['result']['score']['raw'])/floatval($statement['result']['score']['max']);
                }
                $userData[$statement['actor']['name']][$statement['object']['id']][] = $info;
                $questions[$statement['object']['id']] = new stdClass();
                $questions[$statement['object']['id']]->maxScore = $info->maxScore;
                $questions[$statement['object']['id']]->title = $statement['object']['definition']['name']['en-US'];
            }
        }

        foreach($questions as $question){
            $maxTotalGrade += $question->maxScore;
        }

        if (sizeof($questions) == 0){
            $return = new stdClass();
            $return->error = "No statements found";
            echo json_encode($return);
            exit;
        }


        if ($maxTotalGrade == 0){
            $return = new stdClass();
            $return->error = "No scores found";
            $return->questions = $questions;
            echo json_encode($return);
            exit;
        }

        foreach($userData as $user => $info) {
            $userScore = 0;
            $userId = get_user_by("login", $user);

            foreach($info as $object => $object_statements){
                usort($userData[$user][$object], function ($a, $b) {
                    return strtotime($a->timestamp) - strtotime($b->timestamp);
                });

                $target = null;
                if ($gradingScheme == 'first'){
                    $target = reset($userData[$user][$object]);
                } else if ($gradingScheme == 'last'){
                    $target = end($userData[$user][$object]);
                } else { // Best
                    foreach ($userData[$user][$object] as $statement){
                        if (is_null($target) || floatval($target->rawScore) < floatval($statement->rawScore)){
                            $target = $statement;
                        }
                    }
                }
                $userScore += $target->rawScore;
                $userData[$user][$object]['target'] = $target;
            }

            $userData[$user]['totalScore'] = $userScore;
            $percentScore =  floatval($userScore/$maxTotalGrade);
            $userData[$user]['percentScore'] = $percentScore;
            do_action('lti_outcome', $percentScore , $userId->ID, $postid, $blogid);
            // usleep(500000);

        }

        $return = new stdClass();
        $return->maxGrade = $maxTotalGrade;
        $return->userData = $userData;
        $return->questions = $questions;
        return json_encode($return);
    }
    
}