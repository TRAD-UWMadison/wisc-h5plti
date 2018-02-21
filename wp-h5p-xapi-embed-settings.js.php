<?php
define('WP_USE_THEMES', false);
$parse_uri = explode( 'wp-content', $_SERVER['SCRIPT_FILENAME'] );
require_once( $parse_uri[0] . 'wp-load.php' );
?>

WP_H5P_XAPI_STATEMENT_URL = "<?php echo plugins_url(); ?>/wp-h5p-xapi/process-xapi-statement.php";
WP_H5P_XAPI_CONTEXTACTIVITY = {
//    id: window.top.location.href,
    id: window.location.href,
    definition: {
        type: 'http://activitystrea.ms/schema/1.0/page',
        name: {
            en: ''
        },
        moreInfo: window.location.href
    }
};
console.debug("WP_H5P_XAPI_CONTEXTACTIVITY:", WP_H5P_XAPI_CONTEXTACTIVITY);
