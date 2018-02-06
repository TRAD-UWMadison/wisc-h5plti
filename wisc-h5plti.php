<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Wisc H5P LTI Outcomes
 * Description:       Used to capture h5p events and send scores back through LTI
 * Version:           0.2
 * Author:            UW-Madison
 * Author URI:
 * Text Domain:       lti
 * License:           MIT
 * GitHub Plugin URI:
 */

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

include_once plugin_dir_path( __FILE__ ) . 'HypothesisFix.php';
include_once 'LearningLockerInterface.php';

// Do our necessary plugin setup and add_action routines.
WiscH5PLTI::setup();

class WiscH5PLTI {

    const ERROR_NOT_CONFIGURED = 'ERROR_NOT_CONFIGURED';
    const GRADING_SCHEME_BEST = 'best';
    const GRADING_SCHEME_FIRST = 'first';
    const GRADING_SCHEME_LAST = 'last';
    const GRADING_SCHEMES = array(
        self::GRADING_SCHEME_BEST,
        self::GRADING_SCHEME_FIRST,
        self::GRADING_SCHEME_LAST
    );
    const DATETIME_FORMAT = 'Y-m-d';
    const EDIT_SCREEN_GRADE_SYNC_ACTION = 'edit_screen_grade_sync';

    const META_KEY_AUTO_SYNC_VALIDATION_ERROR = 'auto_sync_validation_error';

    private static $learning_locker_settings = array();


    // WordPress Related -----------------------------------------------------------------------------------------------

    public static function setup(){

        add_action('add_meta_boxes_chapter', array(__CLASS__, 'on_add_meta_boxes_chapter'));
        add_action('wp_ajax_edit_screen_grade_sync', array(__CLASS__, 'edit_screen_grade_sync'));

        add_action('admin_notices', array(__CLASS__, 'display_auto_sync_validation_notice'));
        add_action('save_post_chapter', array(__CLASS__, 'save_chapter_grade_sync_meta_box'));

        // Include a custom rolled Hypothesis (plugin) loading script provided by the Hypothesis team.  This loading
        // script will allow h5p embedding within Hypothesis annotations.
        add_action( 'wp', array('HypothesisFix', 'add_custom_hypothesis'), 100);
    }

    public static function display_auto_sync_validation_notice() {
        global $post;
        if (!is_admin() || $post->post_type != "chapter") {
            return;
        }

        $auto_sync_validation_error = get_post_meta($post->ID, self::META_KEY_AUTO_SYNC_VALIDATION_ERROR, true);
        if ($auto_sync_validation_error) {
            ?>
            <div class="notice notice-error"><?php echo $auto_sync_validation_error; ?></div>
            <?php
        }

    }

    public static function on_add_meta_boxes_chapter($post) {
        // Sanity check
        if (is_admin() && $post->post_type == "chapter") {

            add_meta_box(
                'll_statements_meta_box',
                __('H5P xAPI Grading', 'll-statements-meta'),
                array(__CLASS__, 'show_chapter_grade_sync_meta_box'),
                'chapter',
                'normal',
                'high'
            );

            // Queue up some scripts
            wp_enqueue_script('wisc-h5plti', plugins_url('wisc-h5plti.js', __FILE__), array('jquery'));
            wp_localize_script('wisc-h5plti', 'chapterGradeSync', array(
                'action' => self::EDIT_SCREEN_GRADE_SYNC_ACTION,
                'ajaxNonce' => wp_create_nonce(self::EDIT_SCREEN_GRADE_SYNC_ACTION),
                'ajaxURL' => admin_url('admin-ajax.php'),
                'blogID' => get_current_blog_id(),
                'postID' => $post->ID,
            ));

            // Queue up some styles
            wp_enqueue_style('wisc-h5plti', plugins_url('wisc-h5plti.css', __FILE__));
        }
    }

    function show_chapter_grade_sync_meta_box() {

        try {
            self::get_learning_locker_settings(get_current_blog_id());
        } catch (ErrorException $e) {
            ?>
                <div>
                    <h3 class='ll-warning'>LRS settings are missing.</h3>
                    <h4 class='ll-warning'>Please check that you have added an LRS via Dashboard -> Settings -> H5P xAPI.</h4>
                </div>
            <?php
            return;
        }

        global $post;
        $meta = get_post_meta( $post->ID, 'chapter_grade_sync_fields', true );
        $chapter_grade_sync_auto_sync_enabled = get_post_meta( $post->ID, 'chapter_grade_sync_auto_sync_enabled', true ) ? true : false;

        ?>

        <div id="wisc-h5plti">
            <input type="hidden" name="chapter_grade_sync_meta_box_nonce" value="<?php echo wp_create_nonce( basename(__FILE__) ); ?>">

            <div class="input-container">
                <label for="chapter_grade_sync_fields[since]">Beginning Date: </label>                  
                <input id="chapter_grade_sync_fields[since]" name="chapter_grade_sync_fields[since]" type="date" value="<?php echo $meta['since']; ?>" />
            </div>
            <div class="input-container">
                <label for="chapter_grade_sync_fields[until]">Ending Date: </label>
                <input id="chapter_grade_sync_fields[until]" name="chapter_grade_sync_fields[until]" type="date" value="<?php echo $meta['until']; ?>" />
            </div>

            <div class="input-container">
                <label for="chapter_grade_sync_fields[grading_scheme]">Grading Scheme</label>
                <select name="chapter_grade_sync_fields[grading_scheme]" id="chapter_grade_sync_fields[grading_scheme]">
                    <?php foreach (self::GRADING_SCHEMES as $grading_scheme) {
                        $title_case = ucfirst($grading_scheme);
                        $selected = strcmp($meta['grading_scheme'], $grading_scheme) == 0 ? 'selected' : '';
                        echo "<option value=\"$grading_scheme\" $selected> $title_case Attempt</option>";
                    } ?>
                </select></div>
            <div class="input-container">
                <label for="chapter_grade_sync_fields[h5p_ids_string]">H5P ids to grade: </label>
                <input name="chapter_grade_sync_fields[h5p_ids_string]" id="chapter_grade_sync_fields[h5p_ids_string]" type="text" value="<?php echo $meta['h5p_ids_string']; ?>"/>
            </div>

            <div class="input-container">
                <label for="chapter_grade_sync_auto_sync_enabled">Auto-Sync enabled: </label>
                <?php $checked = $chapter_grade_sync_auto_sync_enabled ? 'checked="checked"' : ''; ?>
                <input name="chapter_grade_sync_auto_sync_enabled" id="chapter_grade_sync_auto_sync_enabled" type="checkbox" value="true" <?php echo $checked; ?> />
            </div>

            <div class="input-container right">
                <input type="button" id="chapter_grade_sync_fields_submit" class="button button-primary button-large" value="Send Grades to LMS" />
            </div>

            <div id='ll-statements'>&nbsp;</div>
        </div>
        <?php

    }

    function save_chapter_grade_sync_meta_box($post_id) {
        // verify nonce
        if ( !wp_verify_nonce( $_POST['chapter_grade_sync_meta_box_nonce'], basename(__FILE__) ) ) {
            return $post_id;
        }
        // check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }
        // check permissions
        if ( 'chapter' === $_POST['post_type'] ) {
            if ( !current_user_can( 'edit_page', $post_id ) ) {
                return $post_id;
            } elseif ( !current_user_can( 'edit_post', $post_id ) ) {
                return $post_id;
            }
        }

        // Clear any existing validation error notification
        $auto_sync_validation_error = get_post_meta($post_id, self::META_KEY_AUTO_SYNC_VALIDATION_ERROR, true);
        if ($auto_sync_validation_error) {
            delete_post_meta($post_id, self::META_KEY_AUTO_SYNC_VALIDATION_ERROR, $auto_sync_validation_error);
        }

        $auto_sync_enabled = isset($_POST['chapter_grade_sync_auto_sync_enabled']) && $_POST['chapter_grade_sync_auto_sync_enabled'] == "true" ? true : false;
        if ($auto_sync_enabled) {
            // Validate the rest of the fields
            $chapter_grade_sync_fields = $_POST['chapter_grade_sync_fields'];
            $grading_scheme =   isset($chapter_grade_sync_fields['grading_scheme']) ? $chapter_grade_sync_fields['grading_scheme'] : null;
            $h5p_ids_string =   isset($chapter_grade_sync_fields['h5p_ids_string']) ? $chapter_grade_sync_fields['h5p_ids_string'] : null;
            $since =            isset($chapter_grade_sync_fields['since']) ? $chapter_grade_sync_fields['since'] : null;
            $until =            isset($chapter_grade_sync_fields['until']) ? $chapter_grade_sync_fields['until'] : null;
            $result = self::validate_chapter_grade_sync_fields($grading_scheme, $h5p_ids_string, $since, $until);
            if ($result !== TRUE) {
                $auto_sync_enabled = false;
                $error_html_list = "<ul>";
                foreach ($result as $error_message) {
                    $error_html_list .= "<li>$error_message</li>";
                }
                $error_html_list .= "</ul>";
                update_post_meta($post_id, self::META_KEY_AUTO_SYNC_VALIDATION_ERROR, "<p>Disabled H5P xAPI Grading Auto-Sync due to the following validation errors:</p> $error_html_list");
            }
        }

        $auto_sync_enabled_old = get_post_meta( $post_id, 'chapter_grade_sync_auto_sync_enabled', true );
        if ($auto_sync_enabled_old !== $auto_sync_enabled) {
            update_post_meta( $post_id, 'chapter_grade_sync_auto_sync_enabled', $auto_sync_enabled );
        }

        $old = get_post_meta( $post_id, 'chapter_grade_sync_fields', true );
        $new = $_POST['chapter_grade_sync_fields'];

        if ( $new && $new !== $old ) {
            update_post_meta( $post_id, 'chapter_grade_sync_fields', $new );
        } elseif ( '' === $new && $old ) {
            delete_post_meta( $post_id, 'chapter_grade_sync_fields', $old );
        }
    }


    // Plugin Functionality --------------------------------------------------------------------------------------------

    /**
     * @param integer|null $blog_id
     * @return array
     * @throws ErrorException
     */
    public static function get_learning_locker_settings($blog_id = null) {
        $current_blog_id = get_current_blog_id();
        if (is_null($blog_id)) {
            $blog_id = $current_blog_id;
        }
        $settings_blog_id = $current_blog_id;
        $blog_switch_flag = false;
        if ($blog_id != $settings_blog_id) {
            $settings_blog_id = $blog_id;
            $blog_switch_flag = true;
        }
        if (is_null(self::$learning_locker_settings[$settings_blog_id])) {
            if ($blog_switch_flag) {
                switch_to_blog($settings_blog_id);
            }
            $settings = h5pxapi_get_auth_settings();
            if (!is_array($settings) ||
                !key_exists('endpoint_url', $settings) || empty($settings['endpoint_url']) ||
                !key_exists('username', $settings) || empty($settings['username']) ||
                !key_exists('password', $settings) || empty($settings['password'])) {
                throw new ErrorException(WiscH5PLTI::ERROR_NOT_CONFIGURED);
            }
            // Ensure a trailing '/'
            if (substr($settings['endpoint_url'], -1) != '/') {
                $settings['endpoint_url'] .= '/';
            }
            self::$learning_locker_settings[$settings_blog_id] = $settings;
            if ($blog_switch_flag) {
                switch_to_blog($current_blog_id);
            }
        }
        return self::$learning_locker_settings[$settings_blog_id];
    }

    public static function sync_all_grades($blog_id) {
        switch_to_blog($blog_id);
        $args = array(
            'post_type' => 'chapter',
            'meta_key' => 'chapter_grade_sync_auto_sync_enabled',
            'meta_value' => '1'
        );
        $query = new WP_Query($args);
        global $post;
        while ( $query->have_posts() ) {
            $query->the_post();
            // Get the meta information
            $chapter_grade_sync_fields = get_post_meta( $post->ID, 'chapter_grade_sync_fields', true );
            $grading_scheme =   isset($chapter_grade_sync_fields['grading_scheme']) ? $chapter_grade_sync_fields['grading_scheme'] : null;
            $h5p_ids_string =   isset($chapter_grade_sync_fields['h5p_ids_string']) ? $chapter_grade_sync_fields['h5p_ids_string'] : null;
            $since =            isset($chapter_grade_sync_fields['since']) ? $chapter_grade_sync_fields['since'] : null;
            $until =            isset($chapter_grade_sync_fields['until']) ? $chapter_grade_sync_fields['until'] : null;
            $response = self::validate_chapter_grade_sync_fields($grading_scheme, $h5p_ids_string, $since, $until);
            if ($response !== TRUE) {
                return;
                // todo: record error with fields
                // todo: disable auto-grade-sync
            }

            $h5p_ids = self::get_h5p_ids_from_string($h5p_ids_string);
            $since = date_create_from_format(self::DATETIME_FORMAT, $since);
            $until = date_create_from_format(self::DATETIME_FORMAT, $until);
            
            $report = self::sync_grades_for_post($blog_id, $post->ID, $h5p_ids, $since, $until, $grading_scheme);
            // todo: create log, react to errors
        }
    }
    
    public static function sync_grades_for_post($blog_id, $post_id, $h5p_ids, $since, $until, $grading_scheme) {
        $statements = LearningLockerInterface::get_h5p_statements($blog_id, $h5p_ids, $since, $until);
        return self::process_and_sync_statements($statements, $blog_id, $post_id, $grading_scheme);
    }

    private static function process_and_sync_statements($statements, $blog_id, $post_id, $grading_scheme) {

        $user_data = array();
        $questions = array();
        $max_total_grade = 0;
        $report = new stdClass();

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
                $user_data[$statement['actor']['name']][$statement['object']['id']][] = $info;
                $questions[$statement['object']['id']] = new stdClass();
                $questions[$statement['object']['id']]->maxScore = $info->maxScore;
                $questions[$statement['object']['id']]->title = $statement['object']['definition']['name']['en-US'];
            }
        }

        foreach($questions as $question){
            $max_total_grade += $question->maxScore;
        }

        if (sizeof($questions) == 0){
            $report->error = "No statements found";
            return $report;
        }


        if ($max_total_grade == 0){
            $report->error = "No scores found";
            $report->questions = $questions;
            return $report;
        }

        foreach($user_data as $user => $info) {
            $userScore = 0;
            $userId = get_user_by("login", $user);

            foreach($info as $object => $object_statements){
                usort($user_data[$user][$object], function ($a, $b) {
                    return strtotime($a->timestamp) - strtotime($b->timestamp);
                });

                $target = null;
                if ($grading_scheme == 'first'){
                    $target = reset($user_data[$user][$object]);
                } else if ($grading_scheme == 'last'){
                    $target = end($user_data[$user][$object]);
                } else { // Best
                    foreach ($user_data[$user][$object] as $statement){
                        if (is_null($target) || floatval($target->rawScore) < floatval($statement->rawScore)){
                            $target = $statement;
                        }
                    }
                }
                $userScore += $target->rawScore;
                $user_data[$user][$object]['target'] = $target;
            }

            $user_data[$user]['totalScore'] = $userScore;
            $percentScore =  floatval($userScore/$max_total_grade);
            $user_data[$user]['percentScore'] = $percentScore;
            do_action('lti_outcome', $percentScore , $userId->ID, $post_id, $blog_id);

        }

        $report->maxGrade = $max_total_grade;
        $report->userData = $user_data;
        $report->questions = $questions;
        return $report;
    }

    public static function edit_screen_grade_sync() {

        $ajax_nonce =       isset($_POST['ajaxNonce']) ? $_POST['ajaxNonce'] : null;

        if (wp_verify_nonce($ajax_nonce, self::EDIT_SCREEN_GRADE_SYNC_ACTION) === FALSE) {
            self::edit_screen_grade_sync_return_error("Invalid token, please refresh and resubmit.");
            return;
        }

        $blog_id =          isset($_POST['blogID']) ? $_POST['blogID'] : null;
        $grading_scheme =   isset($_POST['gradingScheme']) ? $_POST['gradingScheme'] : null;
        $h5p_ids_string =   isset($_POST['h5pIDsString']) ? $_POST['h5pIDsString'] : null;
        $post_id =          isset($_POST['postID']) ? $_POST['postID'] : null;
        $since =            isset($_POST['since']) ? $_POST['since'] : null;
        $until =            isset($_POST['until']) ? $_POST['until'] : null;

        $result = self::validate_chapter_grade_sync_fields($grading_scheme, $h5p_ids_string, $since, $until);

        if ($result !== TRUE) {
            $error = join("<br />", $result);
            self::edit_screen_grade_sync_return_error($error);
            return;
        }

        $h5p_ids = self::get_h5p_ids_from_string($h5p_ids_string);
        $since = date_create_from_format(self::DATETIME_FORMAT, $since);
        $until = date_create_from_format(self::DATETIME_FORMAT, $until);

        $report = self::sync_grades_for_post($blog_id, $post_id, $h5p_ids, $since, $until, $grading_scheme);
        echo json_encode($report);
        
        wp_die();
    }
    
    public static function edit_screen_grade_sync_return_error($message) {
        $error_obj = new stdClass();
        $error_obj->error = $message;
        echo json_encode($error_obj);
        wp_die();
    }

    /**
     * @param string $grading_scheme
     * @param string $h5p_ids_string
     * @param string $since
     * @param string $until
     * @return array|bool Returns TRUE if everything validates, returns an array of error messages (stings) on
     *      validation error(s).
     */
    private static function validate_chapter_grade_sync_fields($grading_scheme, $h5p_ids_string, $since, $until) {

        $error_messages = array();


        if (empty($grading_scheme) || array_search($grading_scheme, self::GRADING_SCHEMES) === FALSE) {
            array_push($error_messages, "Invalid grading scheme.");
        }

        if (empty($since)) {
            array_push($error_messages, "Beginning date is required.");
        } else {
            $since = date_create_from_format(self::DATETIME_FORMAT, $since);
            if ($since === FALSE) {
                array_push($error_messages, "Invalid beginning date.");
            }
        }

        if (empty($until)) {
            array_push($error_messages, "Ending date is required.");
        } else {
            $until = date_create_from_format(self::DATETIME_FORMAT, $until);
            if ($until === FALSE) {
                array_push($error_messages, "Invalid ending date.");
            }
        }

        if (preg_match('/^[\s\d,]*$/', $h5p_ids_string) != 1) {
            array_push($error_messages, "Invalid H5P ID(s) - Only comma separated numbers are allowed.");
        } else {
            if (preg_match('/\d(\s)+\d/', $h5p_ids_string) == 1) {
                array_push($error_messages, "Check your H5P ID(s), all values must be comma separated.");
            } else {
                $h5p_ids = self::get_h5p_ids_from_string($h5p_ids_string);
                if (count($h5p_ids) == 0) {
                    array_push($error_messages, "No H5P IDs were submitted.");
                }
            }
        }

        return count($error_messages) == 0 ? TRUE : $error_messages;
    }

    private static function get_h5p_ids_from_string($h5p_ids_string) {
        $h5p_ids = array();
        $h5p_id_strings = explode(',', $h5p_ids_string);
        foreach ($h5p_id_strings as $h5p_id_string) {
            $h5p_id_string = trim($h5p_id_string);
            if (is_numeric($h5p_id_string)) {
                array_push($h5p_ids, $h5p_id_string);
            }
        }
        return $h5p_ids;
    }
    
}