<?php
/*
Plugin Name: Broken Link Scanner
Plugin URI: https://github.com/dominic-g/broken-link-scanner
Description: Finds broken links and sends them to the admin URL on an interval.
Version: 1.0
Author: Dominic_Gitau
Author URI: https://dominicn.dev
License: GPL2
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function check_for_broken_links() {
    $links = array();

    $posts_links = get_posts( array(
        'numberposts' => -1,
        'post_type' => 'any',
        'post_status' => 'publish',
        'fields' => 'ids',
        'meta_query' => array(
            array(
                'key' => '_link_to',
                'compare' => 'EXISTS'
            )
        )
    ) );

    foreach ( $posts_links as $link_id ) {
        $post_content = get_post_field( 'post_content', $link_id );
        preg_match_all( '/href="([^"]+)"/i', $post_content, $matches );

        foreach ( $matches[1] as $url ) {
            $links[] = $url;
        }
    }

    $menus = wp_get_nav_menus();
    foreach ( $menus as $menu ) {
        $menu_items = wp_get_nav_menu_items( $menu->term_id );

        foreach ( $menu_items as $menu_item ) {
            if ( isset( $menu_item->url ) ) {
                $links[] = $menu_item->url;
            }
        }
    }

    $links = array_filter( array_unique( $links ) );

    foreach ( $links as $link ) {
        $response = wp_remote_head( $link );
        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            $subject = 'Broken Link: ' . $link;
            $body = 'The following link is broken: ' . $link;
            wp_mail( get_option( 'admin_email' ), $subject, $body );
        }
    }
}

function schedule_link_checker() {
    if ( ! wp_next_scheduled( 'check_for_broken_links' ) ) {
        wp_schedule_event( time(), 'daily', 'check_for_broken_links' );
    }
}

add_action( 'check_for_broken_links', 'check_for_broken_links' );

add_action( 'init', 'schedule_link_checker' );
