<?php
/*
Plugin Name: Schedule Post to Draft
Description: Allows scheduling of posts to transition to draft status.
Version: 1.0
Author: Stephen Russell
*/

function sptd_add_custom_box() {
    $screens = ['post', 'page'];  // Default post types
    $custom_post_types = get_post_types(array('public' => true, '_builtin' => false));

    $screens = array_merge($screens, $custom_post_types);

    foreach ($screens as $screen) {
        add_meta_box(
            'sptd_box_id',
            'Schedule Draft',
            'sptd_custom_box_html',
            $screen,
            'side'
        );
    }
}

add_action('add_meta_boxes', 'sptd_add_custom_box');

function sptd_custom_box_html($post) {
    $value = get_post_meta($post->ID, '_sptd_datetime', true);
    echo '<label for="sptd_datetime">Schedule Draft on:</label>';
    echo '<input type="datetime-local" id="sptd_datetime" name="sptd_datetime" value="' . esc_attr($value) . '">';
    wp_nonce_field('sptd_save_postdata', 'sptd_nonce');
}

function sptd_save_postdata($post_id) {
    if (!isset($_POST['sptd_nonce']) || !wp_verify_nonce($_POST['sptd_nonce'], 'sptd_save_postdata')) {
        return;
    }

    if (array_key_exists('sptd_datetime', $_POST)) {
        update_post_meta(
            $post_id,
            '_sptd_datetime',
            $_POST['sptd_datetime']
        );
    }
}

add_action('save_post', 'sptd_save_postdata');

function sptd_cron_hook() {
    error_log("Cron job triggered.");

    $args = array(
        'post_status' => 'publish',
        'meta_query'  => array(
            array(
                'key'     => '_sptd_datetime',
                'value'   => current_time('mysql', true),
                'type'    => 'DATETIME',
                'compare' => '<=',
            )
        ),
        'posts_per_page' => -1
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $scheduled_time = get_post_meta($post_id, '_sptd_datetime', true);
            $timestamp = strtotime($scheduled_time);

            error_log("Checking post: $post_id scheduled for $scheduled_time.");

            if ($timestamp <= current_time('timestamp')) {
                wp_update_post(array(
                    'ID'          => $post_id,
                    'post_status' => 'draft'
                ));
                error_log("Post ID $post_id set to draft.");
            }
        }
    } else {
        error_log("No posts found to set to draft.");
    }
}

add_action('sptd_cron_hook', 'sptd_cron_hook');

function sptd_activate() {
    if (!wp_next_scheduled('sptd_cron_hook')) {
        wp_schedule_event(time(), 'hourly', 'sptd_cron_hook');
    }
}

function sptd_deactivate() {
    wp_clear_scheduled_hook('sptd_cron_hook');
}

register_activation_hook(__FILE__, 'sptd_activate');
register_deactivation_hook(__FILE__, 'sptd_deactivate');
