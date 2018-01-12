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


// Do our necessary plugin setup and add_action routines.
WISC_LL_STATEMENTS::init();


class WISC_LL_STATEMENTS{

    public static function init(){
        if (isset($_GET['content_only'])) {
//            wp_enqueue_style('wisc-h5pltinav', plugins_url('no-navigation.css', __FILE__));
        }
//        wp_enqueue_style('wisc-h5plti', plugins_url('wisc-h5plti.css', __FILE__));
        add_action('init', array('WISC_LL_STATEMENTS', 'delayed_init'));
        add_action('add_meta_boxes', array(__CLASS__,'wisc_h5plti_addStatementMetabox'));
//        add_action('h5p_additional_scripts', array(__CLASS__, 'h5p_embed_additional_scripts'), 10, 1);

        // Include a custom rolled Hypothesis (plugin) loading script provided by the Hypothesis team.  This loading
        // script will allow h5p embedding within Hypothesis annotations.
        wp_enqueue_script('hypothosis-core.js', "https://hypothesis-h5p.s3.us-east-2.amazonaws.com/boot.js");
    }

//    function h5p_embed_additional_scripts(&$additional_scripts) {
//        $additional_scripts[] = '<script src="' . plugins_url('wisc-h5plti.js', __FILE__) . '" type="text/javascript"></script>';
//    }


    public static function delayed_init() {
        wp_enqueue_style('wisc-h5plti', plugins_url('wisc-h5plti.css', __FILE__));
    }

    function wisc_h5plti_addStatementMetabox($post)
    {
        add_meta_box('ll_statements_meta_box', __('H5P xAPI Grading', 'll-statements-meta'), array(__CLASS__,'get_learning_locker_statements'), 'chapter', 'normal', 'high');
//    add_meta_box( 'll_statements_meta_box', __( 'xAPI Grading', 'll-statements-meta' ), 'get_learning_locker_statements', 'post', 'normal', 'low');
    }

    function get_learning_locker_statements()
    {
        global $wpdb;

        $current_blog = get_current_blog_id();
        switch_to_blog($current_blog);
        $post_id = get_the_ID();

        $settings=h5pxapi_get_auth_settings();

        $endpoint = $settings["endpoint_url"];
        $auth_user = $settings["username"];
        $auth_password = $settings["password"];

        $auth = base64_encode($auth_user . ":" . $auth_password);

        if (is_null($endpoint) || empty($endpoint) || is_null($auth_user) || empty($auth_user) || is_null($auth_password) || empty($auth_password)) {
            echo "<div><h3 class='ll-warning'>LRS settings are missing.</h3><h4 class='ll-warning'>Please check that you have added an LRS in the grassblade setttings.</h4></div>";

            return;
        }

        wp_enqueue_script('jquery');

        wp_enqueue_script('wisc-h5plti', plugins_url('wisc-h5plti.js', __FILE__), array('jquery'));

        $xapi_process_file = plugins_url('/process-xapi-statements.php', __FILE__);

        $activity_id = get_site_url() . "/wp-admin/admin-ajax.php?action=h5p_embed&id=";

        $view_statements_form = "<div>";
        $view_statements_form .= "<input id='ll-submit-activity' type='hidden' value='" . $activity_id . "'>";
        $view_statements_form .= "<input id='ll-submit-endpoint' type='hidden' value='" . $endpoint . "'>";
        $view_statements_form .= "<input id='ll-submit-auth' type='hidden' value='" . $auth . "'>";
        $view_statements_form .= "<input id='ll-submit-blog' type='hidden' value='" . $current_blog . "'>";
        $view_statements_form .= "<input id='ll-submit-post' type='hidden' value='" . $post_id . "'>";
        $view_statements_form .= "<input id='ll-process-xapi' type='hidden' value='" . $xapi_process_file . "'>";

        $view_statements_form .= '<div class="input-container"><label>Beginning Date: </label><input id="since" type="date"></div>';
        $view_statements_form .= '<div class="input-container"><label>Ending Date: </label><input id="until" type="date"></div>';

        $view_statements_form .= '<div class="input-container"><label>Grading Scheme</label><select id="ll-submit-grading">';
        $view_statements_form .= '<option value="best">Best Attempt</option>';
        $view_statements_form .= '<option value="first">First Attempt</option>';
        $view_statements_form .= '<option value="last">Last Attempt</option>';
        $view_statements_form .= '</select></div>';
        $view_statements_form .= '<div class="input-container"><label>H5P ids to grade: </label><input id="ids" type="text"></div>';

        $view_statements_form .= '<div class="float-right"><input type="button" id="ll-submit-attempted" class="button button-primary button-large" value="Send Grades to LMS"></input></div>';

        $view_statements_form .= "<div id='ll-statements'></div>";
        $view_statements_form .= "</div>";
        echo $view_statements_form;

    }
}