# Plan d'apprentissage : plugin "equipe-foot-custom"

> **Mode de collaboration** : Arnaud écrit le code. Claude explique, donne des indices, relit
> le code après coup — Claude n'implémente pas à la place d'Arnaud (voir
> `docs/superpowers/specs/2026-07-10-plugin-equipe-foot-custom-design.md` pour le design complet).
> Ce document est **vivant** : à mettre à jour au fil des sessions (statut, notes de relecture).

**Dossier de travail** : `src/plugins/equipe-foot-custom/`
**Environnement de test** : `./bin/wp` (WP-CLI dans Docker), `http://localhost:8080/wp-admin`

## Légende de statut

- `[ ]` à faire — `[~]` en cours — `[x]` fait et validé en relecture

---

## Phase 0 — Squelette du plugin `[x]`

**Objectif** : un plugin actif et reconnu par WordPress, prêt à accueillir le code des phases
suivantes.

**Exercices :**
1. `[x]` Compléter l'en-tête de `equipe-foot-custom.php` (Plugin Name, Description, Version,
   Author) — regarde le format attendu dans un plugin WordPress standard (commentaire PHP en
   haut de fichier).
2. `[x]` Créer les dossiers `includes/` et `templates/` (vides pour l'instant).
3. `[x]` Activer le plugin via `./bin/wp plugin activate equipe-foot-custom` et vérifier avec
   `./bin/wp plugin list` qu'il apparaît bien en `active`.

**Indices :** cherche "Plugin Name" dans le Plugin Handbook WordPress. Un plugin n'a pas besoin
de `style.css` contrairement à un thème.

**Question à te poser avant de continuer :** pourquoi ce code devrait-il vivre dans un plugin
plutôt que dans le thème, concrètement — que se passe-t-il si demain tu changes de thème ?

---

## Phase 1 — CPT `joueurs` dans le plugin `[x]`

**Objectif** : `register_post_type( 'joueurs' )` + `register_post_meta` pour
`date_naissance`/`poste`/`numero_prefere`, dans `includes/cpt-joueurs.php`, chargé depuis
`equipe-foot-custom.php`.

**Exercices :**
1. `[x]` Créer `includes/class-cpt-joueurs.php` (choix perso : classe `CPT_Joueurs` plutôt
   qu'un fichier procédural — variante acceptée), écrire la méthode d'enregistrement du CPT,
   l'accrocher à `init`.
2. `[x]` Dans `equipe-foot-custom.php`, ajouter le `require_once` qui charge ce fichier.
3. `[x]` Ajouter les 3 `register_post_meta` (mêmes types qu'avant : string/date, string, integer).
4. `[x]` Vérifier : CPT et post meta confirmés via `wp eval` (`get_post_type_object`,
   `get_registered_meta_keys`).

**Indices :** tu as déjà écrit ce code une fois dans `functions.php` du thème — inspire-toi de
la structure mais retape-le, ne copie-colle pas (l'objectif est de mémoriser la syntaxe).
Attention à bien utiliser `require_once` et pas `require` (pourquoi, à ton avis ?).

---

## Phase 2 — Meta box admin `[x]`

**Objectif** : formulaire dans l'écran d'édition d'un joueur pour saisir les 3 champs, avec
sauvegarde sécurisée.

> **Décision d'architecture (2026-07-10)** : le plugin est finalement organisé en **classes**
> (une classe = un fichier `includes/class-*.php`), pas en fichiers procéduraux comme prévu
> initialement. La meta box vit dans sa propre classe `Meta_Box_Joueurs`
> (`includes/class-meta-box-joueurs.php`), séparée de `CPT_Joueurs` — responsabilité unique.
> Les phases suivantes (taxonomie, entraîneurs, cron) suivront la même logique : une classe
> dédiée par responsabilité, instanciée dans `equipe-foot-custom.php`.

**Exercices :**
1. `[ ]` Créer `includes/class-meta-box-joueurs.php` avec une classe `Meta_Box_Joueurs`, dont
   le constructeur accroche `add_meta_boxes` et `save_post_joueurs`.
2. `[ ]` Écrire la méthode d'affichage : 3 `<input>` pré-remplis avec `get_post_meta`, plus un
   `wp_nonce_field`.
3. `[ ]` Écrire la méthode de sauvegarde : vérifier le nonce, les permissions
   (`current_user_can`), exclure `DOING_AUTOSAVE`, puis `update_post_meta` avec la bonne
   fonction de sanitation selon le type de champ.
4. `[ ]` Dans `equipe-foot-custom.php`, charger le fichier (`require_once`) et instancier
   `new Meta_Box_Joueurs();`, à côté de `new CPT_Joueurs();`.
5. `[ ]` Retirer la méthode `joueurs_sauvegarder_meta()` orpheline de `CPT_Joueurs` (elle
   déménage dans `Meta_Box_Joueurs`).
6. `[ ]` Tester dans l'admin : remplir le formulaire, sauvegarder, vérifier avec
   `./bin/wp post meta list <ID>`.

**Indices :** cherche `add_meta_box()` et `wp_verify_nonce()` dans le Plugin Handbook. Piège
classique : oublier de vérifier le nonce AVANT de faire quoi que ce soit d'autre dans le
callback de sauvegarde — pourquoi l'ordre des vérifications compte-t-il ?

---

## Phase 3 — Taxonomie + calculs catégorie/âge `[x]`

**Objectif** : taxonomie `categorie_joueur` (hiérarchique), fonctions de calcul de saison et de
catégorie (avec passage automatique en `"Senior"` à partir de U19), assignation automatique à
la sauvegarde.

**Exercices :**
1. `[ ]` `includes/taxonomie-categorie.php` : `register_taxonomy( 'categorie_joueur', 'joueurs', ... )`,
   hiérarchique, `show_admin_column => true`.
2. `[ ]` `includes/calculs.php` : fonction qui détermine l'année de fin de saison actuelle
   (règle du 1er septembre — tu l'as déjà écrite une fois, remets-la de mémoire).
3. `[ ]` Toujours dans `calculs.php` : fonction qui calcule la catégorie à partir d'une date de
   naissance. **Nouveau par rapport à la dernière fois** : si le nombre calculé est `>= 19`,
   retourne `"Senior"` au lieu de `"U19"`, `"U20"`, etc.
4. `[ ]` Fonction de calcul d'âge (comme précédemment, non stockée).
5. `[ ]` Assignation automatique : hook sur `save_post_joueurs` (priorité après la sauvegarde
   des meta) qui relit `date_naissance`, calcule la catégorie, l'assigne via
   `wp_set_object_terms`.
6. `[ ]` Vérifier avec `./bin/wp eval` sur plusieurs dates de naissance : une qui donne un U
   normal, une qui donne "Senior".

**Indices :** attention à l'ordre des hooks `save_post_joueurs` (le paramètre priorité de
`add_action`) — le calcul de catégorie a besoin que `date_naissance` soit déjà sauvegardée en
base à ce moment-là.

---

## Phase 4 — CPT `entraineurs` `[x]`

**Objectif** : un second CPT simple (titre + photo), pour pratiquer la répétition du pattern
de la Phase 1 sur un cas différent.

**Exercices :**
1. `[x]` `includes/class-cpt-entraineurs.php` : `register_post_type( 'entraineurs' )`, support
   `title` + `thumbnail` uniquement, classe `CPT_Entraineurs`.
2. `[x]` Charger le fichier depuis `equipe-foot-custom.php`.
3. `[x]` Lier la taxonomie `categorie_joueur` aux entraîneurs : second argument de
   `register_taxonomy` passé en tableau `array( 'joueurs', 'entraineurs' )` — une seule
   taxonomie partagée, cohérence des données garantie.

---

## Phase 5 — Recalcul annuel (wp-cron) `[x]`

**Objectif** : le recalcul automatique des catégories chaque 1er septembre, comme dans le
thème, mais déclenché différemment (un plugin n'a pas `after_switch_theme`).

**Exercices :**
1. `[ ]` `includes/cron-recalcul.php` : ajouter l'intervalle `yearly` via le filtre
   `cron_schedules`.
2. `[ ]` Planifier l'événement via `register_activation_hook( __FILE__, ... )` (à la place de
   `after_switch_theme`) — calcul de la prochaine date du 1er septembre.
3. `[ ]` `register_deactivation_hook` pour nettoyer avec `wp_clear_scheduled_hook`.
4. `[ ]` Fonction de recalcul en masse (boucle sur tous les joueurs via `get_posts`).
5. `[ ]` Vérifier : désactiver/réactiver le plugin (`./bin/wp plugin deactivate` puis
   `activate`), vérifier avec `./bin/wp cron event list`, puis forcer l'exécution avec
   `./bin/wp cron event run`.

**Indices :** `register_activation_hook` prend `__FILE__` du fichier principal du plugin — si
tu l'appelles depuis `includes/cron-recalcul.php`, ça ne fonctionnera pas comme prévu. Où doit
être placé cet appel ?

---

## Phase 6 — Affichage front `single-joueurs.php` `[ ]`

**Objectif** : une vraie fiche joueur visible sur le site (photo, nom, poste, numéro, âge,
catégorie), en résolvant le problème spécifique aux templates de plugin.

**Exercices :**
1. `[ ]` Créer `templates/single-joueurs.php` avec la Boucle WordPress standard (`have_posts`,
   `the_post`), affichant les infos du joueur.
2. `[ ]` Dans `equipe-foot-custom.php` (ou un fichier dédié), écrire un filtre sur
   `template_include` : si `is_singular( 'joueurs' )`, retourner le chemin vers le template du
   plugin ; sinon, laisser WordPress faire comme d'habitude.
3. `[ ]` Vérifier en visitant l'URL d'un joueur publié sur le site (pas juste dans l'admin).

**Indices :** cherche "template_include" dans la documentation WordPress. Attention à ne
jamais retourner un chemin qui n'existe pas (sinon page blanche) — dans quel cas faut-il
absolument renvoyer le `$template` d'origine sans y toucher ?

---

## Notes de session (à compléter au fil du travail)

- 2026-07-10 : plan créé et validé. Dossier `equipe-foot-custom` créé vide via `make plugin`.
  Prochaine étape : Phase 0.
- 2026-07-10 : Phase 0 terminée et validée en relecture. En-tête de plugin correcte, dossiers
  créés, plugin activé et confirmé via `./bin/wp plugin list`. Note : le plugin a son propre
  dépôt git séparé (`git@github.com-perso:ArnaudFlament35/equipe-foot-custom.git`), imbriqué
  dans le dépôt principal `tests-wp` — à surveiller car git peut le traiter comme un
  sous-module non déclaré (gitlink) si on `git add` ce dossier depuis la racine `tests-wp`.
  Prochaine étape : Phase 1 (CPT joueurs).
- 2026-07-10 : Phase 1 terminée et validée. Choix de conception : classe `CPT_Joueurs`
  (`includes/class-cpt-joueurs.php`) plutôt qu'un fichier procédural — regroupe CPT +
  (bientôt) meta box dans une seule classe. Bugs trouvés en relecture et corrigés par
  l'utilisateur : callback de meta box cassé (retiré temporairement), dépendance cachée à
  `theme_log()` du thème (remplacée par `equipe_foot_custom_log()` propre au plugin, basé sur
  `EQUIPE_FOOT_CUSTOM_DIR`), dashicon invalide (`dashicons-admin-BuddyPress` →
  `dashicons-buddicons-buddypress-logo`), `register_post_meta` manquant (ajouté). Reste en
  code mort mineur, non bloquant : `joueurs_sauvegarder_meta()` toujours accrochée à
  `save_post_joueurs` mais sans meta box pour la nourrir — sera retravaillée en Phase 2.
  Prochaine étape : Phase 2 (meta box admin).
- 2026-07-10 : Phase 2 terminée et validée. Classe `Meta_Box_Joueurs`
  (`includes/class-meta-box-joueurs.php`), séparée de `CPT_Joueurs`. Bug trouvé en relecture :
  `register_post_meta` avait été déplacé vers `Meta_Box_Joueurs` sans être accroché à un hook
  (méthode jamais appelée, meta désenregistrées) — corrigé en le remettant dans `CPT_Joueurs`
  sur `init`, décision de design confirmée (la donnée appartient au CPT, pas à l'admin UI).
  Sauvegarde testée de bout en bout dans l'admin (post ID 38) : les 3 champs se sauvegardent
  correctement. Prochaine étape : Phase 3 (taxonomie + calculs catégorie/âge).
- 2026-07-10 : Phase 3 terminée et validée. Classes `Taxonomie_Categorie` et `Calculs_Joueurs`
  (une classe par responsabilité, cohérent avec les phases précédentes). Seuil Senior (>18,
  soit >=19) testé sur les bornes U18/Senior et sur des cas anciens : correct. Assignation
  automatique testée sur un vrai post (post 38, passage à Senior confirmé en base). Prochaine
  étape : Phase 4 (CPT entraineurs).
- 2026-07-10 : fin de session. Phases 0 à 3 terminées et validées. Prochaine étape à la
  reprise : Phase 4 (CPT `entraineurs`, pattern identique à `CPT_Joueurs` en Phase 1, support
  `title` + `thumbnail` uniquement, classe `CPT_Entraineurs` dans
  `includes/class-cpt-entraineurs.php`) — rien commencé sur cette phase pour l'instant.
- 2026-07-16 : Phase 4 terminée et validée. CPT `entraineurs` déclaré (`class-cpt-entraineurs.php`).
  Taxonomie `categorie_joueur` étendue aux entraîneurs (second argument de `register_taxonomy`
  en tableau). Prochaine étape : Phase 5 (recalcul annuel via wp-cron).
- 2026-07-16 : Phase 5 terminée et validée. Classe `Cron_Recalcul` (`includes/class-cron-recalcul.php`).
  Méthode statique `on_activate` pour la planification (appelée via `register_activation_hook`),
  `register_deactivation_hook` pour le nettoyage. Événement `equipe_foot_custom_cron_recalcul`
  confirmé via `./bin/wp cron event list` : planifié au 2026-09-01 00:00:00, récurrence 1 an.
  Prochaine étape : Phase 6 (affichage front `single-joueurs.php`).
- 2026-07-17 : Phase 6 en cours. `templates/single-joueurs.php` créé (boucle standard +
  affichage photo/date de naissance/poste/numéro préféré via `get_post_meta`). Filtre choisi :
  `single_template` (variante acceptée par rapport à `template_include` prévu au plan — plus
  ciblé puisque spécifique aux singles) dans `equipe-foot-custom.php`, fonction
  `get_custom_post_type_template()`. Bugs trouvés et corrigés en session :
  1) `require_once` du template directement dans `equipe-foot-custom.php` (en plus du filtre) —
     exécutait le template au chargement du plugin, avant que `$wp_query` existe → fatal error
     `get_queried_object() on null`. Supprimé, seul le filtre `single_template` doit charger le
     template.
  2) Site en thème par blocs (`twentytwentyfive-child-theme`, enfant de Twenty Twenty-Five, FSE)
     sans `header.php`/`footer.php` classiques → `get_header()`/`get_footer()` génèrent des
     avertissements "Deprecated". Décision : adapter le template pour construire le squelette
     HTML à la main (`wp_head()`, `wp_body_open()`, `wp_footer()`) plutôt que changer de thème.
  3) Balises `<html>`/`<body>` avec appel PHP inline (`language_attributes()`, `body_class()`)
     sans le `>` de fermeture → structure HTML cassée, page noire. Corrigé.
  4) Page blanche ensuite : fausse alerte, `the_content()` était juste vide sur le post de test
     (normal, l'affichage doit venir des post meta, pas du contenu de l'article).
  **Reste à corriger avant de valider la phase :** ligne du numéro préféré utilise la clé
  `joueurs_numero_prefere` au lieu de `numero_prefere` (nom réel enregistré dans
  `class-cpt-joueurs.php`) — le champ ne s'affiche pas à cause de cette faute de frappe.
  Reste aussi à afficher âge et catégorie (via `Calculs_Joueurs` et la taxonomie) — pas encore
  fait dans le template. Prochaine étape à la reprise : corriger la clé meta, ajouter âge +
  catégorie à l'affichage, puis valider la Phase 6 en visitant une fiche joueur publiée.
- 2026-07-17 : Phase 6 en cours. `templates/single-joueurs.php` créé (boucle standard +
  affichage photo/date de naissance/poste/numéro préféré via `get_post_meta`). Filtre choisi :
  `single_template` (variante acceptée par rapport à `template_include` prévu au plan — plus
  ciblé puisque spécifique aux singles) dans `equipe-foot-custom.php`, fonction
  `get_custom_post_type_template()`. Bugs trouvés et corrigés en session :
  1) `require_once` du template directement dans `equipe-foot-custom.php` (en plus du filtre) —
     exécutait le template au chargement du plugin, avant que `$wp_query` existe → fatal error
     `get_queried_object() on null`. Supprimé, seul le filtre `single_template` doit charger le
     template.
  2) Site en thème par blocs (`twentytwentyfive-child-theme`, enfant de Twenty Twenty-Five, FSE)
     sans `header.php`/`footer.php` classiques → `get_header()`/`get_footer()` génèrent des
     avertissements "Deprecated". Décision : adapter le template pour construire le squelette
     HTML à la main (`wp_head()`, `wp_body_open()`, `wp_footer()`) plutôt que changer de thème.
  3) Balises `<html>`/`<body>` avec appel PHP inline (`language_attributes()`, `body_class()`)
     sans le `>` de fermeture → structure HTML cassée, page noire. Corrigé.
  4) Page blanche ensuite : fausse alerte, `the_content()` était juste vide sur le post de test
     (normal, l'affichage doit venir des post meta, pas du contenu de l'article).
  **Reste à corriger avant de valider la phase :** ligne du numéro préféré utilise la clé
  `joueurs_numero_prefere` au lieu de `numero_prefere` (nom réel enregistré dans
  `class-cpt-joueurs.php`) — le champ ne s'affiche pas à cause de cette faute de frappe.
  Reste aussi à afficher âge et catégorie (via `Calculs_Joueurs` et la taxonomie) — pas encore
  fait dans le template. Prochaine étape à la reprise : corriger la clé meta, ajouter âge +
  catégorie à l'affichage, puis valider la Phase 6 en visitant une fiche joueur publiée.
