<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Wisc H5P LTI Outcomes
 * Description:       Used to capture h5p events and send scores back through LTI
 * Version:           0.1
 * Author:            UW-Madison
 * Author URI:
 * Text Domain:       lti
 * License:           MIT
 * GitHub Plugin URI:
 */

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

include_once 'LearningLockerInterface.php';

// Do our necessary plugin setup and add_action routines.
WiscH5PLTI::setup();


class WiscH5PLTI {

    const ERROR_NOT_CONFIGURED = 'ERROR_NOT_CONFIGURED';

    private static $learning_locker_settings = array();


    // WordPress Related -----------------------------------------------------------------------------------------------


    public static function setup(){
        add_action('init', array(__CLASS__, 'delayed_init'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_chapter_grade_sync_meta_box'));
        add_action('save_post', array(__CLASS__, 'save_chapter_grade_sync_meta_box'));

        // Include a custom rolled Hypothesis (plugin) loading script provided by the Hypothesis team.  This loading
        // script will allow h5p embedding within Hypothesis annotations.
        wp_enqueue_script('hypothosis-core.js', "https://hypothesis-h5p.s3.us-east-2.amazonaws.com/boot.js");
    }

    public static function delayed_init() {
        wp_enqueue_style('wisc-h5plti', plugins_url('wisc-h5plti.css', __FILE__));
    }

    function add_chapter_grade_sync_meta_box($post) {
        add_meta_box(
            'll_statements_meta_box',
            __('H5P xAPI Grading', 'll-statements-meta'),
            array(__CLASS__, 'show_chapter_grade_sync_meta_box'),
            'chapter',
            'normal',
            'high'
        );
    }

    function show_chapter_grade_sync_meta_box() {

        global $post;
        $meta = get_post_meta( $post->ID, 'chapter_grade_sync_fields', true );

        echo '<input type="hidden" name="chapter_grade_sync_meta_box_nonce" value="' . wp_create_nonce( basename(__FILE__) ) . '">';

//        $current_blog = get_current_blog_id();
//        switch_to_blog($current_blog);
//        $post_id = get_the_ID();

        $settings = h5pxapi_get_auth_settings(); // todo - use helper

//        $endpoint = $settings["endpoint_url"];
//        $auth_user = $settings["username"];
//        $auth_password = $settings["password"];
//        $auth = base64_encode($auth_user . ":" . $auth_password);

        if (is_null($settings) || empty($settings) ||
            is_null($settings["endpoint_url"]) || empty($settings["endpoint_url"]) ||
            is_null($settings["username"]) || empty($settings["username"]) ||
            is_null($settings["password"]) || empty($settings["password"])) {
            echo "<div><h3 class='ll-warning'>LRS settings are missing.</h3><h4 class='ll-warning'>Please check that you have added an LRS in the grassblade setttings.</h4></div>";
            return;
        }

//        wp_enqueue_script('jquery');
        wp_enqueue_script('wisc-h5plti', plugins_url('wisc-h5plti.js', __FILE__), array('jquery'));

//        $xapi_process_file = plugins_url('/process-xapi-statements.php', __FILE__);
//        $activity_id = get_site_url() . "/wp-admin/admin-ajax.php?action=h5p_embed&id=";

        ?>
        <div>
            <input type="hidden" name="chapter_grade_sync_meta_box_nonce" value="<?php echo wp_create_nonce( basename(__FILE__) ); ?>">
<!--            <input type='hidden' name="chapter_grade_sync_fields[activity_url]" id='ll-submit-activity'    value='--><?php //echo $activity_id; ?><!--'>-->
<!--            <input type='hidden' name="chapter_grade_sync_fields[endpoint_url]" id='ll-submit-endpoint'    value='--><?php //echo $endpoint; ?><!--'>-->
<!--            <input type='hidden' name="chapter_grade_sync_fields[_____]" id='ll-submit-auth'        value='--><?php //echo $auth; ?><!--'>-->
<!--            <input type='hidden' name="chapter_grade_sync_fields[_____]" id='ll-submit-blog'        value='--><?php //echo $current_blog; ?><!--'>-->
<!--            <input type='hidden' name="chapter_grade_sync_fields[_____]" id='ll-submit-post'        value='--><?php //echo $post_id; ?><!--'>-->
<!--            <input type='hidden' name="chapter_grade_sync_fields[_____]" id='ll-process-xapi'       value='--><?php //echo $xapi_process_file; ?><!--'>-->

            <div class="input-container">
                <label for="chapter_grade_sync_fields[since]">Beginning Date: </label>                  
                <input id="chapter_grade_sync_fields[since]" name="chapter_grade_sync_fields[since]" type="date" />
            </div>
            <div class="input-container">
                <label for="chapter_grade_sync_fields[until]">Ending Date: </label>
                <input id="chapter_grade_sync_fields[until]" name="chapter_grade_sync_fields[until]" type="date" />
            </div>

            <div class="input-container">
                <label for="chapter_grade_sync_fields[grading_scheme]">Grading Scheme</label>
                <select name="chapter_grade_sync_fields[grading_scheme]" id="chapter_grade_sync_fields[grading_scheme]">
                    <option value="best">Best Attempt</option>
                    <option value="first">First Attempt</option>
                    <option value="last">Last Attempt</option>
                </select></div>
            <div class="input-container">
                <label for="chapter_grade_sync_fields[ids]">H5P ids to grade: </label>
                <input name="chapter_grade_sync_fields[ids]" id="chapter_grade_sync_fields[ids]" type="text" />
            </div>

            <div class="input-container">
                <label for="chapter_grade_sync_fields[auto_sync_enabled]">Auto-Sync enabled: </label>
                <input name="chapter_grade_sync_fields[auto_sync_enabled]" id="chapter_grade_sync_fields[auto_sync_enabled]" type="checkbox" />
            </div>

<!--            <div class="float-right">-->
<!--                <input type="button" id="ll-submit-attempted" class="button button-primary button-large" value="Send Grades to LMS" />-->
<!--            </div>-->

            <div id='ll-statements'></div>
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


}