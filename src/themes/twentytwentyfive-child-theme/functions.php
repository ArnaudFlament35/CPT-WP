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
// Ajout d'un cpt

function twentytwentyfive_child_register_joueurs_post_types() {
	// La déclaration de nos Custom Post Types et Taxonomies ira ici
     // CPT Portfolio
     $labels = array(
        'name' => 'Joueurs',
        'all_items' => 'Tous les joueurs',  // affiché dans le sous menu
        'singular_name' => 'Joueur',
        'add_new_item' => 'Ajouter un joueur',
        'edit_item' => 'Modifier le joueur',
        'menu_name' => 'Joueurs'
    );

	$args = array(
        'labels' => $labels,
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'supports' => array( 'title', 'editor','thumbnail' ),
        'menu_position' => 5, 
        'menu_icon' => 'dashicons-admin-BuddyPress',
	);

	register_post_type( 'joueurs', $args );
}
add_action( 'init', 'twentytwentyfive_child_register_joueurs_post_types' );
function joueurs_register_meta() {
    $fields = array(
        'date_naissance' => array(
            'type'   => 'string',
            'format' => 'date',
        ),
        'poste' => array(
            'type' => 'string',
        ),
        'numero_prefere' => array(
            'type' => 'integer',
        ),
    );

    foreach ( $fields as $key => $config ) {
        register_post_meta( 'joueurs', $key, array(
            'type'              => $config['type'],
            'single'            => true,
            'show_in_rest'      => true, // indispensable : expose le champ dans l'API REST + block editor
            'sanitize_callback' => $config['type'] === 'integer' ? 'absint' : 'sanitize_text_field',
            'auth_callback'     => function() {
                return current_user_can( 'edit_posts' );
            },
        ) );
    }
}
add_action( 'init', 'joueurs_register_meta' );