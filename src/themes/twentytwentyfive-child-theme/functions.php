<?php

define('THEME_LOG', '/var/log/php/error.log');

function theme_log( $message ) {
    $line = '[' . date('H:i:s') . '] ' . print_r($message, true) . PHP_EOL;
    error_log($line, 3, THEME_LOG);
}

add_action('wp_enqueue_scripts', 'twentytwentyfive_child_enqueue_styles');
function twentytwentyfive_child_enqueue_styles()
{
    theme_log('Enqueueing child theme styles');
    wp_enqueue_style(
        'twentytwentyfive-child-style',
        get_stylesheet_uri(),
        array(),
        wp_get_theme()->get('Version')
    );
}

add_action('init', 'twentytwentyfive_child_theme_setup');
function twentytwentyfive_child_theme_setup()
{
    theme_log('init déclenché');
}
