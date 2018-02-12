<?php

/**
 * Author: Ian McNamara <ian.mcnamara@wisc.edu>
 *         Teaching and Research Application Development
 * Copyright 2018 Board of Regents of the University of Wisconsin System
 */
class H5PGradeSyncError {

    const SLUG = 'h5p-grade-sync-error';

    const META_AUTO_GRADE_SYNC_DISABLED =   self::SLUG . '_meta_auto_grade_sync_disabled';
    const META_ERROR_MESSAGE =              self::SLUG . '_error_message';
    const META_POST_ID =                    self::SLUG . '_post_id';
    const META_POST_TITLE =                 self::SLUG . '_post_title';

    public static function setup() {
        add_action( 'add_meta_boxes_' . self::SLUG , array( __CLASS__, 'add_meta_box' ) );
        add_action( 'init', array( __CLASS__, 'init_custom_post_type' ) );
        add_action( 'manage_' . self::SLUG . '_posts_custom_column' , array( __CLASS__, 'custom_columns' ), 10, 2 );
        add_action( 'manage_edit-' . self::SLUG . '_columns', array( __CLASS__, 'edit_columns' ) );
        add_filter( 'manage_edit-' . self::SLUG . '_sortable_columns', array( __CLASS__, 'edit_sortable_columns' ) ) ;
    }

    public static function edit_columns( $columns ) {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'title' => 'Title',
            self::META_POST_TITLE => 'Title',
            self::META_POST_ID => 'ID',
            self::META_ERROR_MESSAGE => 'Error',
            self::META_AUTO_GRADE_SYNC_DISABLED => 'Auto Sync Disabled',
        );
        return $columns;
    }

    public static function edit_sortable_columns( $columns ) {
        $columns[self::META_AUTO_GRADE_SYNC_DISABLED] = self::META_AUTO_GRADE_SYNC_DISABLED;
        $columns[self::META_ERROR_MESSAGE] = self::META_ERROR_MESSAGE;
        $columns[self::META_POST_ID] = self::META_POST_ID;
        $columns[self::META_POST_TITLE] = self::META_POST_TITLE;
        return $columns;
    }

    public static function custom_columns( $column, $post_id ) {

        switch( $column ) {
            case self::META_AUTO_GRADE_SYNC_DISABLED:
                if ( get_post_meta( $post_id, $column, true ) === '1') {
                    echo '<span style="color:#f88;">&#10004;</span>';
                }
                break;
            case self::META_ERROR_MESSAGE:
                $meta_value = get_post_meta( $post_id, $column, true);
                echo $meta_value;
                break;
            case self::META_POST_ID:
            case self::META_POST_TITLE:
                $chapter_id = get_post_meta( $post_id, self::META_POST_ID, true);
                $edit_chapter_url = admin_url() . "post.php?post=" . $chapter_id . "&action=edit";
                $meta_value = get_post_meta( $post_id, $column, true);
                echo '<a href="' . $edit_chapter_url . '">' . $meta_value . '</a>';
                break;
            default:
                break;
        }

    }

    public static function add_meta_box() {
        add_meta_box(
            'h5p-grade-sync-error-content',
            'Error Report',
            array( __CLASS__, 'print_meta_box' ),
            self::SLUG,
            'normal',
            'high'
        );
    }

    public static function print_meta_box() {
        global $post;
        echo $post->post_content;
    }

    public static function init_custom_post_type() {

        // Register the post type
        $labels = array(
            'add_new'            => 'VOID',
            'add_new_item'       => 'VOID',
            'all_items'          => 'All Errors',
            'edit_item'          => 'Edit Error',
            'name'               => 'H5P Grade Sync Errors',
            'new_item'           => 'VOID',
            'singular_name'      => 'H5P Grade Sync Error',
            'view_item'          => 'VOID',
        );
        $supports = array(
            'title'
        );
        $capabilities = array(
            'create_posts' => 'do_not_allow',
        );
        register_post_type(
            self::SLUG,
            array(
                'capabilities' => $capabilities,
                'capability_type' => 'post',
                'description' => 'Errors from the H5P Auto Grade Sync',
                'exclude_from_search' => true,
                'has_archive' => false,
                'labels' => $labels,
                'map_meta_cap' => true,
                'public' => false,
                'publicly_queryable' => false,
                'show_ui' => true,
                'supports' => $supports,
                'taxonomies' => array(),
            )
        );
    }

    /**
     * @param string $post_title
     * @param int $post_id
     * @param string $since
     * @param string $until
     * @param string $grading_scheme
     * @param string $h5p_ids_string
     * @param array $error_messages
     * @param boolean $auto_grade_sync_disabled
     */
    public static function create_error($post_title, $post_id, $since, $until, $grading_scheme, $h5p_ids_string, $error_messages, $auto_grade_sync_disabled) {

        $edit_post_url = admin_url() . "post.php?post=" . $post_id . "&action=edit";
        $post_content = '
                <p>Error(s) were encountered:</p>
                <ul><li>' . join("</li></li>", $error_messages) . '</li></ul>' .
                ( $auto_grade_sync_disabled ? '<p>Auto grade sync was disabled for this chapter.</p>' : '') . '
                <table>
                    <tr>
                        <th>Title</th>   
                        <th>Post ID</th>   
                        <th>Since</th>   
                        <th>Until</th>   
                        <th>Grading Scheme</th>   
                        <th>H5P IDs</th>   
                    </tr>
                    <tr>
                        <td><a href="' . $edit_post_url . '">' . $post_title . '</a></td>
                        <td><a href="' . $edit_post_url . '">' . $post_id . '</a></td>
                        <td>' . $since . '</td>
                        <td>' . $until . '</td>
                        <td>' . $grading_scheme . '</td>
                        <td>' . $h5p_ids_string . '</td>
                    </tr>
                </table>
        ';

        $datetime = new DateTime();
        $datetime->setTimezone(new DateTimeZone('America/Chicago'));
        $log_title = $datetime->format("Y-m-d H:i:s") . " Post:$post_id";

        $error_id = wp_insert_post( array(
            'post_content' => $post_content,
            'post_title' => $log_title,
            'post_status' => 'private',
            'post_type' => self::SLUG,
        ) );
        if ( is_numeric($error_id) && $error_id > 0 ) {
            // Attach meta
            update_post_meta($error_id, self::META_AUTO_GRADE_SYNC_DISABLED, $auto_grade_sync_disabled);
            update_post_meta($error_id, self::META_ERROR_MESSAGE, join('; ', $error_messages));
            update_post_meta($error_id, self::META_POST_ID, $post_id);
            update_post_meta($error_id, self::META_POST_TITLE, $post_title);
        }
    }


}

H5PGradeSyncError::setup();