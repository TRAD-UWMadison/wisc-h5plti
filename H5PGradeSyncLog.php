<?php

/**
 * Author: Ian McNamara <ian.mcnamara@wisc.edu>
 *         Teaching and Research Application Development
 * Copyright 2018 Board of Regents of the University of Wisconsin System
 */
class H5PGradeSyncLog {

    const SLUG = 'h5p-grade-sync-log';

    public static function setup() {
        add_action( 'add_meta_boxes_' . H5PGradeSyncLog::SLUG , array( __CLASS__, 'add_meta_box' ) );
        add_action( 'init', array( 'H5PGradeSyncLog', 'init_custom_post_type' ) );
    }

    public static function add_meta_box() {
        add_meta_box(
            'h5p-grade-sync-log-content',
            'Log Report',
            array( 'H5PGradeSyncLog', 'print_meta_box' ),
            H5PGradeSyncLog::SLUG,
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
            'all_items'          => 'All Logs',
            'edit_item'          => 'Edit Log',
            'name'               => 'H5P Grade Sync Logs',
            'new_item'           => 'VOID',
            'singular_name'      => 'H5P Grade Sync Log',
            'view_item'          => 'VOID',
        );
        $supports = array(
            'title'
        );
        $capabilities = array(
            'create_posts' => 'do_not_allow',
        );
        register_post_type(
            H5PGradeSyncLog::SLUG,
            array(
                'capabilities' => $capabilities,
                'capability_type' => 'post',
                'description' => 'Logs from the H5P Auto Grade Sync',
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

}

H5PGradeSyncLog::setup();