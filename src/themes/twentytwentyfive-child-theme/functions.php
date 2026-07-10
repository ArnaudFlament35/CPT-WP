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

// --- Meta box : informations du joueur ---

add_action( 'add_meta_boxes', 'joueurs_ajouter_meta_box' );
function joueurs_ajouter_meta_box() {
    add_meta_box(
        'joueurs_infos',
        'Informations du joueur',
        'joueurs_afficher_meta_box',
        'joueurs',
        'normal',
        'high'
    );
}

function joueurs_afficher_meta_box( $post ) {
    wp_nonce_field( 'joueurs_sauvegarder_meta', 'joueurs_meta_nonce' );

    $date_naissance = get_post_meta( $post->ID, 'date_naissance', true );
    $poste          = get_post_meta( $post->ID, 'poste', true );
    $numero_prefere = get_post_meta( $post->ID, 'numero_prefere', true );
    ?>
    <p>
        <label for="joueurs_date_naissance">Date de naissance</label><br>
        <input type="date" id="joueurs_date_naissance" name="joueurs_date_naissance" value="<?php echo esc_attr( $date_naissance ); ?>">
    </p>
    <p>
        <label for="joueurs_poste">Poste</label><br>
        <input type="text" id="joueurs_poste" name="joueurs_poste" value="<?php echo esc_attr( $poste ); ?>">
    </p>
    <p>
        <label for="joueurs_numero_prefere">Numéro préféré</label><br>
        <input type="number" id="joueurs_numero_prefere" name="joueurs_numero_prefere" value="<?php echo esc_attr( $numero_prefere ); ?>">
    </p>
    <?php
}

add_action( 'save_post_joueurs', 'joueurs_sauvegarder_meta' );
function joueurs_sauvegarder_meta( $post_id ) {
    if ( ! isset( $_POST['joueurs_meta_nonce'] ) || ! wp_verify_nonce( $_POST['joueurs_meta_nonce'], 'joueurs_sauvegarder_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['joueurs_date_naissance'] ) ) {
        update_post_meta( $post_id, 'date_naissance', sanitize_text_field( $_POST['joueurs_date_naissance'] ) );
    }
    if ( isset( $_POST['joueurs_poste'] ) ) {
        update_post_meta( $post_id, 'poste', sanitize_text_field( $_POST['joueurs_poste'] ) );
    }
    if ( isset( $_POST['joueurs_numero_prefere'] ) ) {
        update_post_meta( $post_id, 'numero_prefere', absint( $_POST['joueurs_numero_prefere'] ) );
    }
}

// --- Taxonomie : catégorie sportive du joueur (U13, U14...) ---

add_action( 'init', 'joueurs_enregistrer_taxonomie_categorie' );
function joueurs_enregistrer_taxonomie_categorie() {
    $labels = array(
        'name'          => 'Catégories',
        'singular_name' => 'Catégorie',
        'menu_name'     => 'Catégories',
    );

    register_taxonomy( 'categorie_joueur', 'joueurs', array(
        'labels'            => $labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
    ) );
}

// --- Fonctions utilitaires : saison, catégorie, âge ---

function joueurs_obtenir_annee_fin_saison() {
    $aujourdhui     = new DateTime();
    $annee_courante = (int) $aujourdhui->format( 'Y' );
    $debut_saison   = DateTime::createFromFormat( 'Y-m-d', $annee_courante . '-09-01' );

    if ( $aujourdhui < $debut_saison ) {
        return $annee_courante;
    }
    return $annee_courante + 1;
}

function joueurs_calculer_categorie( $date_naissance ) {
    if ( empty( $date_naissance ) ) {
        return '';
    }
    $naissance = DateTime::createFromFormat( 'Y-m-d', $date_naissance );
    if ( ! $naissance ) {
        return '';
    }

    $annee_naissance  = (int) $naissance->format( 'Y' );
    $annee_fin_saison = joueurs_obtenir_annee_fin_saison();

    return 'U' . ( $annee_fin_saison - $annee_naissance );
}

function joueurs_calculer_age( $date_naissance ) {
    if ( empty( $date_naissance ) ) {
        return null;
    }
    $naissance = DateTime::createFromFormat( 'Y-m-d', $date_naissance );
    if ( ! $naissance ) {
        return null;
    }

    $aujourdhui = new DateTime();
    return (int) $aujourdhui->diff( $naissance )->y;
}