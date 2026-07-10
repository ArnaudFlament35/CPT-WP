# Champs personnalisés + catégorie sportive auto-calculée ("joueurs") Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter une meta box admin pour les champs `date_naissance`/`poste`/`numero_prefere` du CPT `joueurs`, et une taxonomie `categorie_joueur` (U13, U14...) calculée automatiquement à partir de l'année de naissance et de la saison sportive (début 1er septembre), avec recalcul à la sauvegarde et via une tâche planifiée annuelle.

**Architecture:** Tout le code vit dans `src/themes/twentytwentyfive-child-theme/functions.php`, à la suite du code existant (CPT + `register_post_meta` déjà en place). Chaque bloc de fonctionnalité est un groupe de fonctions procédurales accrochées à des hooks WordPress (`add_meta_box`, `save_post_joueurs`, `init`, `wp_schedule_event`), dans le style déjà utilisé dans le fichier.

**Tech Stack:** PHP procédural + hooks WordPress. Pas de PHPUnit/wp-env dans ce projet : la vérification de chaque tâche se fait via **WP-CLI** (`./bin/wp` ou `make wp`, qui tournent dans le container Docker défini par `docker-compose.yml`) et par une vérification visuelle dans `/wp-admin`. C'est l'équivalent "test" adapté à ce projet WordPress sans harnais PHPUnit.

## Global Constraints

- Le CPT `joueurs` et les 3 `register_post_meta` existants (`date_naissance`, `poste`, `numero_prefere`) ne doivent pas être modifiés dans leur déclaration (lignes `functions.php:29-79`).
- Nonce + `current_user_can` obligatoires sur toute sauvegarde déclenchée par un formulaire admin (règle sécurité PHP du projet).
- La saison sportive commence le 1er septembre (en dur, pas de réglage admin — hors scope).
- Catégorie = année de fin de saison − année de naissance, formatée `"U" . $nombre` (ex: `U13`).
- Aucun affichage front-end dans ce plan (hors scope, laissé à l'utilisateur).

---

### Task 1: Meta box admin pour les champs du joueur

**Files:**
- Modify: `src/themes/twentytwentyfive-child-theme/functions.php` (ajouter à la fin du fichier, après la ligne 80)

**Interfaces:**
- Produces: `joueurs_ajouter_meta_box()`, `joueurs_afficher_meta_box( WP_Post $post )`, `joueurs_sauvegarder_meta( int $post_id )` — noms de champs meta réutilisés par la Task 4 : `date_naissance`, `poste`, `numero_prefere`.

- [ ] **Step 1: Ajouter la déclaration de la meta box**

Ajoute à la fin de `functions.php` :

```php

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
```

- [ ] **Step 2: Ajouter la sauvegarde sécurisée**

Ajoute juste après le bloc précédent :

```php

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
```

- [ ] **Step 3: Vérifier via WP-CLI qu'un joueur de test existe**

Run: `./bin/wp post create --post_type=joueurs --post_title="Joueur Test" --post_status=publish`
Expected: sortie du type `Success: Created post <ID>.`

- [ ] **Step 4: Vérification visuelle dans l'admin**

Ouvre `http://localhost:8080/wp-admin/post.php?post=<ID>&action=edit` (remplace `<ID>` par l'ID retourné à l'étape précédente). La meta box "Informations du joueur" doit apparaître sous l'éditeur, avec les 3 champs. Remplis-les (ex: `2014-05-12`, `Attaquant`, `9`) et clique sur "Mettre à jour".

- [ ] **Step 5: Vérifier que les meta sont bien sauvegardées**

Run: `./bin/wp post meta list <ID>`
Expected: la sortie liste `date_naissance` = `2014-05-12`, `poste` = `Attaquant`, `numero_prefere` = `9`.

- [ ] **Step 6: Commit**

```bash
git add src/themes/twentytwentyfive-child-theme/functions.php
git commit -m "feat: ajoute la meta box admin pour les champs du joueur"
```

---

### Task 2: Taxonomie hiérarchique `categorie_joueur`

**Files:**
- Modify: `src/themes/twentytwentyfive-child-theme/functions.php` (ajouter à la suite de la Task 1)

**Interfaces:**
- Produces: taxonomie `categorie_joueur` enregistrée sur le CPT `joueurs`. Réutilisée par la Task 4 (`wp_set_object_terms`) et la Task 5.

- [ ] **Step 1: Ajouter l'enregistrement de la taxonomie**

```php

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
```

- [ ] **Step 2: Vérifier que la taxonomie est bien enregistrée**

Run: `./bin/wp taxonomy list --fields=name,object_type`
Expected: une ligne `categorie_joueur` avec `object_type` contenant `joueurs`.

- [ ] **Step 3: Commit**

```bash
git add src/themes/twentytwentyfive-child-theme/functions.php
git commit -m "feat: ajoute la taxonomie categorie_joueur"
```

---

### Task 3: Fonctions utilitaires de calcul (saison, catégorie, âge)

**Files:**
- Modify: `src/themes/twentytwentyfive-child-theme/functions.php` (ajouter à la suite de la Task 2)

**Interfaces:**
- Consumes: rien (fonctions pures, pas d'accès à la base de données).
- Produces: `joueurs_obtenir_annee_fin_saison() : int`, `joueurs_calculer_categorie( string $date_naissance ) : string`, `joueurs_calculer_age( string $date_naissance ) : ?int` — réutilisées par la Task 4 et la Task 5.

- [ ] **Step 1: Ajouter les fonctions de calcul**

```php

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
```

- [ ] **Step 2: Vérifier le calcul de catégorie via WP-CLI**

Run: `./bin/wp eval "echo joueurs_calculer_categorie( '2014-05-12' );"`
Expected: affiche `U13` (ou `U12` selon la date du jour réelle par rapport au 1er septembre — vérifie que le calcul correspond bien à la règle : année de fin de saison − 2014).

- [ ] **Step 3: Vérifier le calcul d'âge via WP-CLI**

Run: `./bin/wp eval "echo joueurs_calculer_age( '2014-05-12' );"`
Expected: affiche l'âge en années entières à la date du jour (ex: `12` si on est avant l'anniversaire 2026, `13` après).

- [ ] **Step 4: Commit**

```bash
git add src/themes/twentytwentyfive-child-theme/functions.php
git commit -m "feat: ajoute les fonctions de calcul de saison/categorie/age"
```

---

### Task 4: Assignation automatique de la catégorie à la sauvegarde

**Files:**
- Modify: `src/themes/twentytwentyfive-child-theme/functions.php` (ajouter à la suite de la Task 3)

**Interfaces:**
- Consumes: `joueurs_calculer_categorie( string $date_naissance ) : string` (Task 3).
- Produces: `joueurs_assigner_categorie( int $post_id )` — réutilisée telle quelle par la Task 5 (recalcul en masse).

- [ ] **Step 1: Ajouter le hook d'assignation**

```php

// --- Assignation automatique de la catégorie ---

add_action( 'save_post_joueurs', 'joueurs_assigner_categorie', 20 );
function joueurs_assigner_categorie( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    $date_naissance = get_post_meta( $post_id, 'date_naissance', true );
    $categorie      = joueurs_calculer_categorie( $date_naissance );

    if ( empty( $categorie ) ) {
        return;
    }

    wp_set_object_terms( $post_id, $categorie, 'categorie_joueur' );
}
```

La priorité `20` (au lieu de la valeur par défaut `10`) garantit que ce hook s'exécute **après** `joueurs_sauvegarder_meta` (Task 1), donc que `date_naissance` est déjà à jour en base au moment du calcul.

- [ ] **Step 2: Vérifier la ré-assignation via WP-CLI**

Reprends le joueur de test créé en Task 1 (`<ID>`) :

Run: `./bin/wp post meta update <ID> date_naissance 2014-05-12 && ./bin/wp post update <ID> --post_title="Joueur Test"`
Expected: la commande `post update` déclenche `save_post_joueurs`, donc l'assignation.

- [ ] **Step 3: Vérifier le terme assigné**

Run: `./bin/wp post term list <ID> categorie_joueur`
Expected: affiche un terme du type `U13` (ou la valeur correspondant à la saison en cours).

- [ ] **Step 4: Commit**

```bash
git add src/themes/twentytwentyfive-child-theme/functions.php
git commit -m "feat: assigne automatiquement la categorie a la sauvegarde"
```

---

### Task 5: Recalcul planifié chaque 1er septembre (cron)

**Files:**
- Modify: `src/themes/twentytwentyfive-child-theme/functions.php` (ajouter à la suite de la Task 4)

**Interfaces:**
- Consumes: `joueurs_assigner_categorie( int $post_id )` (Task 4).
- Produces: événement cron `joueurs_recalculer_categories_event`, planification `yearly`.

- [ ] **Step 1: Ajouter l'intervalle cron "yearly" (WordPress ne le fournit pas nativement)**

```php

// --- Recalcul annuel des catégories (chaque 1er septembre) ---

add_filter( 'cron_schedules', 'joueurs_ajouter_planification_annuelle' );
function joueurs_ajouter_planification_annuelle( $schedules ) {
    $schedules['yearly'] = array(
        'interval' => YEAR_IN_SECONDS,
        'display'  => 'Une fois par an',
    );
    return $schedules;
}
```

- [ ] **Step 2: Planifier l'événement à l'activation du thème et le nettoyer à la désactivation**

```php

add_action( 'after_switch_theme', 'joueurs_planifier_recalcul_categories' );
function joueurs_planifier_recalcul_categories() {
    if ( wp_next_scheduled( 'joueurs_recalculer_categories_event' ) ) {
        return;
    }

    $annee             = (int) date( 'Y' );
    $prochaine_rentree = strtotime( $annee . '-09-01' );

    if ( $prochaine_rentree < time() ) {
        $prochaine_rentree = strtotime( ( $annee + 1 ) . '-09-01' );
    }

    wp_schedule_event( $prochaine_rentree, 'yearly', 'joueurs_recalculer_categories_event' );
}

add_action( 'switch_theme', 'joueurs_annuler_recalcul_categories' );
function joueurs_annuler_recalcul_categories() {
    wp_clear_scheduled_hook( 'joueurs_recalculer_categories_event' );
}
```

- [ ] **Step 3: Ajouter le gestionnaire de l'événement (recalcul en masse)**

```php

add_action( 'joueurs_recalculer_categories_event', 'joueurs_recalculer_toutes_categories' );
function joueurs_recalculer_toutes_categories() {
    $ids_joueurs = get_posts( array(
        'post_type'      => 'joueurs',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );

    foreach ( $ids_joueurs as $post_id ) {
        joueurs_assigner_categorie( $post_id );
    }
}
```

- [ ] **Step 4: Déclencher la replanification (le hook `after_switch_theme` ne se déclenche qu'à l'activation du thème)**

Run: `./bin/wp theme activate twentytwentyfive-child-theme`
Expected: `Success: Switched to 'twentytwentyfive-child-theme' theme.`

- [ ] **Step 5: Vérifier que l'événement est bien planifié**

Run: `./bin/wp cron event list --fields=hook,next_run_relative | grep joueurs_recalculer_categories_event`
Expected: une ligne avec `joueurs_recalculer_categories_event` et un `next_run_relative` correspondant à la prochaine rentrée de septembre.

- [ ] **Step 6: Vérifier manuellement le recalcul en masse (sans attendre septembre)**

Run: `./bin/wp cron event run joueurs_recalculer_categories_event`
Expected: `Success: Executed the cron event 'joueurs_recalculer_categories_event' successfully.` Puis reconfirme avec `./bin/wp post term list <ID> categorie_joueur` que le terme est toujours cohérent.

- [ ] **Step 7: Commit**

```bash
git add src/themes/twentytwentyfive-child-theme/functions.php
git commit -m "feat: planifie le recalcul annuel des categories via wp-cron"
```

---

## Self-Review

1. **Spec coverage** : meta box (Task 1) ✓, taxonomie hiérarchique (Task 2) ✓, calcul saison/catégorie (Task 3) ✓, assignation à la sauvegarde (Task 4) ✓, cron annuel 1er septembre (Task 5) ✓, fonction âge non stockée (Task 3, `joueurs_calculer_age`) ✓. Affichage front explicitement hors scope, aucune tâche ne le couvre — conforme au design.
2. **Placeholder scan** : aucun "TODO"/"TBD" ; chaque step contient le code complet ou la commande exacte.
3. **Type consistency** : `joueurs_calculer_categorie( string $date_naissance ) : string` (Task 3) est bien le nom et la signature réutilisés en Task 4 (`joueurs_assigner_categorie`) et Task 5 (via `joueurs_assigner_categorie`). `date_naissance`/`poste`/`numero_prefere` sont les mêmes clés meta partout (déjà déclarées dans le fichier existant).
