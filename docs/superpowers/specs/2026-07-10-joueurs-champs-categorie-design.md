# Design : Champs personnalisés + catégorie sportive auto-calculée pour le CPT "joueurs"

Date : 2026-07-10
Fichier concerné : `src/themes/twentytwentyfive-child-theme/functions.php`

## Contexte

Le CPT `joueurs` est déjà déclaré, avec 3 champs meta déjà enregistrés via `register_post_meta()` :
`date_naissance`, `poste`, `numero_prefere`. Il manque :
- un formulaire dans l'admin pour saisir ces champs (pas de `add_meta_box`),
- une catégorie sportive (U13, U14...) qui doit se déduire automatiquement de l'année de
  naissance et de la saison en cours, pas être saisie à la main.

L'affichage front-end est explicitement **hors scope** de cette session : l'utilisateur s'en
chargera lui-même ensuite pour s'exercer.

## 1. Meta box PHP classique (admin)

- `add_meta_box()` accroché à l'écran d'édition du CPT `joueurs`.
- Champs affichés : `date_naissance` (`<input type="date">`), `poste` (texte libre),
  `numero_prefere` (`<input type="number">`).
- Sauvegarde sur `save_post_joueurs` :
  - vérification du nonce (`wp_verify_nonce`),
  - vérification des permissions (`current_user_can( 'edit_post', $post_id )`),
  - exclusion des autosaves (`DOING_AUTOSAVE`),
  - `update_post_meta()` pour chaque champ avec sanitation adaptée au type
    (`sanitize_text_field`, `absint`).

## 2. Taxonomie hiérarchique `categorie_joueur`

- `register_taxonomy( 'categorie_joueur', 'joueurs', [...] )`, hiérarchique (comme les
  catégories d'articles, pas les tags), pour bénéficier des pages d'archive
  (`/categorie_joueur/u13/`) et du filtrage natif dans l'admin.
- Les termes (`U13`, `U14`, ...) sont créés automatiquement à la volée s'ils n'existent pas
  encore (pas de création manuelle préalable).

## 3. Règle de calcul de la catégorie

- **Saison en cours** : si la date du jour est avant le 1er septembre de l'année en cours,
  la saison est `(année-1)/année` ; sinon `année/(année+1)`.
- **Catégorie** = année de fin de saison − année de naissance.
  - Exemple : né en 2014, on est en saison 2026/2027 (fin 2027) → 2027 − 2014 = **U13**.
- Deux fonctions utilitaires pures, sans effet de bord :
  - `joueurs_obtenir_annee_fin_saison() : int`
  - `joueurs_calculer_categorie( string $date_naissance ) : string` (retourne `"U13"`, etc.)

## 4. Déclenchement du recalcul

- **À la sauvegarde d'un joueur** : sur `save_post_joueurs`, après la sauvegarde des meta,
  on recalcule la catégorie du joueur concerné et on l'assigne via
  `wp_set_object_terms( $post_id, $categorie, 'categorie_joueur' )`.
- **Chaque 1er septembre, automatiquement** : un événement `wp_schedule_event()` (planifié à
  l'activation, via un hook du type `after_switch_theme`, avec nettoyage sur
  désactivation) qui boucle sur tous les posts `joueurs` (`WP_Query`) et recalcule/réassigne
  leur catégorie. Cela garantit que les catégories changent même si personne n'édite les
  fiches au changement de saison.

## 5. Âge affiché (hors persistance)

- `joueurs_calculer_age( string $date_naissance ) : int`, fonction utilitaire simple
  (basée sur `DateTime::diff`), pas de nouveau champ meta. Utilisable plus tard côté front.
  Pas de branchement front dans cette session.

## Hors scope

- Affichage front-end (templates, shortcodes...) — laissé à l'utilisateur.
- Interface de configuration de la règle de saison (le 1er septembre est en dur dans le code,
  pas un réglage admin).

## Points de vigilance identifiés

- Le recalcul cron doit gérer un grand nombre de joueurs sans timeout (pagination `WP_Query`
  si besoin, mais volumétrie attendue faible pour un club/école).
- Le nonce et les vérifications de permissions dans la meta box sont obligatoires pour éviter
  toute modification non autorisée des données d'un joueur (CSRF / accès non autorisé).
