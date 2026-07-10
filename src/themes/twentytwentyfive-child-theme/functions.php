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

// function twentytwentyfive_child_register_joueurs_post_types() {
// 	// La déclaration de nos Custom Post Types et Taxonomies ira ici
//      // CPT Portfolio
//      $labels = array(
//         'name' => 'Joueurs',
//         'all_items' => 'Tous les joueurs',  // affiché dans le sous menu
//         'singular_name' => 'Joueur',
//         'add_new_item' => 'Ajouter un joueur',
//         'edit_item' => 'Modifier le joueur',
//         'menu_name' => 'Joueurs'
//     );

// 	$args = array(
//         'labels' => $labels,
//         'public' => true,
//         'show_in_rest' => true,
//         'has_archive' => true,
//         'supports' => array( 'title', 'editor','thumbnail' ),
//         'menu_position' => 5, 
//         'menu_icon' => 'dashicons-admin-BuddyPress',
// 	);

// 	register_post_type( 'joueurs', $args );
// }
// add_action( 'init', 'twentytwentyfive_child_register_joueurs_post_types' );
// function joueurs_register_meta() {
//     $fields = array(
//         'date_naissance' => array(
//             'type'   => 'string',
//             'format' => 'date',
//         ),
//         'poste' => array(
//             'type' => 'string',
//         ),
//         'numero_prefere' => array(
//             'type' => 'integer',
//         ),
//     );

//     foreach ( $fields as $key => $config ) {
//         register_post_meta( 'joueurs', $key, array(
//             'type'              => $config['type'],
//             'single'            => true,
//             'show_in_rest'      => true, // indispensable : expose le champ dans l'API REST + block editor
//             'sanitize_callback' => $config['type'] === 'integer' ? 'absint' : 'sanitize_text_field',
//             'auth_callback'     => function() {
//                 return current_user_can( 'edit_posts' );
//             },
//         ) );
//     }
// }
// add_action( 'init', 'joueurs_register_meta' );



// // --- Taxonomie : catégorie sportive du joueur (U13, U14...) ---

// add_action( 'init', 'joueurs_enregistrer_taxonomie_categorie' );
// function joueurs_enregistrer_taxonomie_categorie() {
//     $labels = array(
//         'name'          => 'Catégories',
//         'singular_name' => 'Catégorie',
//         'menu_name'     => 'Catégories',
//     );

//     register_taxonomy( 'categorie_joueur', 'joueurs', array(
//         'labels'            => $labels,
//         'hierarchical'      => true,
//         'public'            => true,
//         'show_in_rest'      => true,
//         'show_admin_column' => true,
//     ) );
// }

// // --- Fonctions utilitaires : saison, catégorie, âge ---

// function joueurs_obtenir_annee_fin_saison() {
//     $aujourdhui     = new DateTime();
//     $annee_courante = (int) $aujourdhui->format( 'Y' );
//     $debut_saison   = DateTime::createFromFormat( 'Y-m-d', $annee_courante . '-09-01' );

//     if ( $aujourdhui < $debut_saison ) {
//         return $annee_courante;
//     }
//     return $annee_courante + 1;
// }

// function joueurs_calculer_categorie( $date_naissance ) {
//     if ( empty( $date_naissance ) ) {
//         return '';
//     }
//     $naissance = DateTime::createFromFormat( 'Y-m-d', $date_naissance );
//     if ( ! $naissance ) {
//         return '';
//     }

//     $annee_naissance  = (int) $naissance->format( 'Y' );
//     $annee_fin_saison = joueurs_obtenir_annee_fin_saison();

//     return 'U' . ( $annee_fin_saison - $annee_naissance );
// }

// function joueurs_calculer_age( $date_naissance ) {
//     if ( empty( $date_naissance ) ) {
//         return null;
//     }
//     $naissance = DateTime::createFromFormat( 'Y-m-d', $date_naissance );
//     if ( ! $naissance ) {
//         return null;
//     }

//     $aujourdhui = new DateTime();
//     return (int) $aujourdhui->diff( $naissance )->y;
// }

// // --- Assignation automatique de la catégorie ---

// add_action( 'save_post_joueurs', 'joueurs_assigner_categorie', 20 );
// function joueurs_assigner_categorie( $post_id ) {
//     if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
//         return;
//     }

//     $date_naissance = get_post_meta( $post_id, 'date_naissance', true );
//     $categorie      = joueurs_calculer_categorie( $date_naissance );

//     if ( empty( $categorie ) ) {
//         return;
//     }

//     wp_set_object_terms( $post_id, $categorie, 'categorie_joueur' );
// }

// // --- Recalcul annuel des catégories (chaque 1er septembre) ---

// add_filter( 'cron_schedules', 'joueurs_ajouter_planification_annuelle' );
// function joueurs_ajouter_planification_annuelle( $schedules ) {
//     $schedules['yearly'] = array(
//         'interval' => YEAR_IN_SECONDS,
//         'display'  => 'Une fois par an',
//     );
//     return $schedules;
// }

// add_action( 'after_switch_theme', 'joueurs_planifier_recalcul_categories' );
// function joueurs_planifier_recalcul_categories() {
//     if ( wp_next_scheduled( 'joueurs_recalculer_categories_event' ) ) {
//         return;
//     }

//     $annee             = (int) date( 'Y' );
//     $prochaine_rentree = strtotime( $annee . '-09-01' );

//     if ( $prochaine_rentree < time() ) {
//         $prochaine_rentree = strtotime( ( $annee + 1 ) . '-09-01' );
//     }

//     wp_schedule_event( $prochaine_rentree, 'yearly', 'joueurs_recalculer_categories_event' );
// }

// add_action( 'switch_theme', 'joueurs_annuler_recalcul_categories' );
// function joueurs_annuler_recalcul_categories() {
//     wp_clear_scheduled_hook( 'joueurs_recalculer_categories_event' );
// }

// add_action( 'joueurs_recalculer_categories_event', 'joueurs_recalculer_toutes_categories' );
// function joueurs_recalculer_toutes_categories() {
//     $ids_joueurs = get_posts( array(
//         'post_type'      => 'joueurs',
//         'posts_per_page' => -1,
//         'fields'         => 'ids',
//     ) );

//     foreach ( $ids_joueurs as $post_id ) {
//         joueurs_assigner_categorie( $post_id );
//     }
// }