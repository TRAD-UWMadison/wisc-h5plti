<?php

/**
 * Author: Ian McNamara <ian.mcnamara@wisc.edu>
 *         Teaching and Research Application Development
 * Copyright 2018 Board of Regents of the University of Wisconsin System
 */
class HypothesisFix {

    /**
     * Recreates the logic used by HypothesisSettingsPage::add_hypothesis to conditionally add the modified
     * Hypothesis boot js file.
     * This was developed against Hypothesis plugin version 0.5.0 and may not work with subsequent versions.
     */
    public static function add_custom_hypothesis() {

        // Require the Hypothesis plugin
        if (!class_exists('HypothesisSettingsPage')) {
            return;
        }

        $options = get_option( 'wp_hypothesis_options' );
        $posttypes = HypothesisSettingsPage::get_posttypes();

        // Set defaults if we $options is not set yet.
        if ( empty( $options ) ) :
            $defaults = array(
                'highlights-on-by-default' => 1,
            );
            add_option( 'wp_hypothesis_options', $defaults );
        endif;


        if ( is_front_page() && isset( $options['allow-on-front-page'] ) ) {
            self::enqueue_custom_hypothesis();
        } elseif ( is_home() && isset( $options['allow-on-blog-page'] ) ) {
            self::enqueue_custom_hypothesis();
        }

        foreach ( $posttypes as $slug => $name ) {
            if ( 'page' !== $slug ) {
                $posttype = $slug;
                if ( 'post' === $slug ) {
                    $slug = 'posts'; // Backwards compatibility.
                }
                if ( isset( $options[ "allow-on-$slug" ] ) && is_singular( $posttype ) ) { // Check if Hypothesis is allowed on this post type.
                    if ( isset( $options[ $posttype . '_ids_override' ] ) && ! is_single( $options[ $posttype . '_ids_override' ] ) ) { // Make sure this post isn't in the override list if it exists.
                        self::enqueue_custom_hypothesis();
                    } elseif ( ! isset( $options[ $posttype . '_ids_override' ] ) ) {
                        self::enqueue_custom_hypothesis();
                    }
                } elseif ( ! isset( $options[ "allow-on-$slug" ] ) && isset( $options[ $posttype . '_ids_show_h' ] ) && is_single( $options[ $posttype . '_ids_show_h' ] ) ) { // Check if Hypothesis is allowed on this specific post.
                    self::enqueue_custom_hypothesis();
                }
            } elseif ( 'page' === $slug ) {
                if ( isset( $options['allow-on-pages'] ) && is_page() && ! is_front_page() && ! is_home() ) { // Check if Hypothesis is allowed on pages (and that we aren't on a special page).
                    if ( isset( $options['page_ids_override'] ) && ! is_page( $options['page_ids_override'] ) ) { // Make sure this page isn't in the override list if it exists.
                        self::enqueue_custom_hypothesis();
                    } elseif ( ! isset( $options['page_ids_override'] ) ) {
                        self::enqueue_custom_hypothesis();
                    }
                } elseif ( ! isset( $options['allow-on-pages'] ) && isset( $options['page_ids_show_h'] ) && is_page( $options['page_ids_show_h'] ) ) { // Check if Hypothesis is allowed on this specific page.
                    self::enqueue_custom_hypothesis();
                }
            }
        }
    }

    private static function enqueue_custom_hypothesis() {
        wp_deregister_script('hypothesis');
        wp_enqueue_script('hypothesis', "https://hypothesis-h5p.s3.us-east-2.amazonaws.com/boot.js");
    }
    
}