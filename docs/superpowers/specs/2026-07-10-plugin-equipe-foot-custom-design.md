# Design : Plugin "equipe-foot-custom" (gestion d'un club de foot)

Date : 2026-07-10
Dossier concerné : `src/plugins/equipe-foot-custom/` (actuellement vide, un seul fichier
`equipe-foot-custom.php` créé via `make plugin`)

## Contexte

Suite à l'exercice précédent (CPT `joueurs` implémenté directement dans le thème enfant),
l'utilisateur veut :
1. Passer à un vrai plugin (bonne pratique : la logique métier ne doit pas dépendre du thème
   actif).
2. Étendre le domaine : joueurs (jeunes ET seniors) + entraîneurs.
3. Ajouter l'affichage front-end `single-joueurs.php`, qui manquait dans l'exercice précédent.
4. **Surtout** : cette fois, c'est l'utilisateur qui écrit le code. Claude accompagne
   (explications, découpage en exercices, relecture), mais n'implémente pas à sa place.

Décision explicite : le CPT `joueurs` existant dans le thème enfant reste tel quel (code mort
à terme, non nettoyé dans ce projet) ; le plugin reconstruit tout depuis zéro, en réutilisant
les mêmes concepts déjà pratiqués (bon renforcement pédagogique par répétition).

## 1. Organisation des fichiers du plugin

```
src/plugins/equipe-foot-custom/
├── equipe-foot-custom.php        → en-tête du plugin + require_once des fichiers ci-dessous
├── includes/
│   ├── cpt-joueurs.php           → register_post_type( 'joueurs' ) + register_post_meta
│   ├── cpt-entraineurs.php       → register_post_type( 'entraineurs' ) (titre + photo pour l'instant)
│   ├── taxonomie-categorie.php   → register_taxonomy( 'categorie_joueur', 'joueurs' )
│   ├── meta-box-joueurs.php      → add_meta_box + save_post_joueurs (nonce, permissions)
│   ├── calculs.php               → saison / catégorie / âge
│   └── cron-recalcul.php         → wp-cron annuel (register_activation_hook au lieu de after_switch_theme)
└── templates/
    └── single-joueurs.php        → template d'affichage front d'un joueur
```

Chaque fichier = une responsabilité. `equipe-foot-custom.php` ne contient que l'en-tête de
plugin standard et les `require_once` — pas de logique métier dedans.

## 2. Règle jeune/senior (différence avec l'exercice précédent)

Même formule qu'avant : `catégorie = année de fin de saison − année de naissance` (saison
démarrant le 1er septembre). Nouveauté : **U18 est la dernière catégorie jeune** ; à partir de
`U19` (nombre calculé ≥ 19), le joueur est classé `"Senior"` au lieu de continuer en U19, U20...
La fonction de calcul retourne donc soit `"U6"`...`"U18"`, soit `"Senior"`.

## 3. CPT `entraineurs`

Version minimale pour cette phase : titre + photo (support `thumbnail`), pas de champs
spécifiques ni de relation avec les catégories/équipes. Les champs spécifiques (diplôme,
équipes encadrées) seront ajoutés dans une phase ultérieure, non planifiée ici.

## 4. Recalcul annuel (wp-cron)

Même mécanique que dans le thème (intervalle `yearly` via `cron_schedules`, recalcul en masse
via `get_posts` + réassignation), mais le déclenchement de la planification change : un plugin
n'a pas de hook `after_switch_theme` — on utilise `register_activation_hook( __FILE__, ... )`
et `register_deactivation_hook` pour nettoyer (`wp_clear_scheduled_hook`).

## 5. Affichage front `single-joueurs.php`

Point technique clé (absent de l'exercice précédent) : les templates d'un **plugin** ne sont
pas automatiquement pris en compte par la hiérarchie de templates de WordPress (qui ne regarde
que le thème actif). Il faut un filtre `template_include` qui vérifie si l'on est sur un
`single` de post type `joueurs`, et qui renvoie le chemin vers
`templates/single-joueurs.php` du plugin si c'est le cas (sans écraser le comportement normal
pour les autres types de contenu).

Contenu de la fiche : photo, nom, poste, numéro préféré, âge (calculé, `joueurs_calculer_age`),
catégorie (terme de taxonomie assigné).

## 6. Mode de collaboration (contrainte transversale, pas une fonctionnalité du plugin)

- Le document de suivi (voir section suivante) découpe le travail en exercices avec objectif +
  indices + fonctions/hooks WordPress à chercher dans la documentation officielle, **sans code
  fourni**.
- L'utilisateur écrit le code. Claude relit ensuite (comme une code review), signale bugs et
  améliorations, mais ne réécrit pas à la place de l'utilisateur sauf demande explicite de
  blocage total.
- Le document de suivi vit dans le dépôt (`docs/plugin-equipe-foot-custom-plan.md`, en dehors
  du dossier `docs/superpowers/` réservé aux plans d'exécution automatisés) et est mis à jour
  au fil des sessions : statut de chaque exercice, notes de relecture, décisions prises.

## Hors scope de cette phase de conception

- Champs spécifiques du CPT `entraineurs` (diplôme, équipe encadrée) — reporté.
- Nettoyage du CPT `joueurs` dans le thème enfant — laissé tel quel pour l'instant.
- Relation entraîneur ↔ catégorie/équipe — reportée.
- Toute interface de réglage admin (la règle du 1er septembre et le seuil U19 restent en dur
  dans le code, comme dans l'exercice précédent).

## Points de vigilance identifiés

- `template_include` doit vérifier précisément le post type (`is_singular( 'joueurs' )`) pour
  ne jamais intercepter l'affichage d'autres contenus du site.
- Le nonce et les vérifications de permissions dans la meta box restent obligatoires (déjà vu,
  mais à re-pratiquer sans filet cette fois).
- `register_activation_hook` ne s'exécute qu'à l'activation du plugin : si le plugin est déjà
  actif au moment d'ajouter le code du cron, il faudra désactiver/réactiver pour déclencher la
  planification (comme on a dû le faire avec `after_switch_theme` dans le thème).
