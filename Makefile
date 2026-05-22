# ============================================================
# Commandes de développement WordPress
# Usage : make <commande>
# ============================================================

.PHONY: help up down restart build logs shell wp install reset theme plugin

# Affiche l'aide par défaut
help:
	@echo ""
	@echo "  Commandes disponibles :"
	@echo ""
	@echo "  Environnement Docker"
	@echo "  --------------------"
	@echo "  make up          Démarrer tous les containers"
	@echo "  make down        Arrêter tous les containers"
	@echo "  make restart     Redémarrer les containers"
	@echo "  make build       Reconstruire l'image WordPress"
	@echo "  make logs        Voir les logs en temps réel"
	@echo "  make shell       Ouvrir un terminal dans le container WordPress"
	@echo ""
	@echo "  WordPress"
	@echo "  ---------"
	@echo "  make install     Installer WordPress (première fois)"
	@echo "  make reset       Supprimer toutes les données et recommencer"
	@echo ""
	@echo "  WP-CLI"
	@echo "  ------"
	@echo "  make wp <cmd>    Exécuter une commande WP-CLI"
	@echo "  Exemples :"
	@echo "    make wp plugin list"
	@echo "    make wp theme list"
	@echo "    make wp user list"
	@echo "    make wp post list"
	@echo ""
	@echo "  Développement"
	@echo "  -------------"
	@echo "  make theme name=mon-theme    Créer un thème vide"
	@echo "  make plugin name=mon-plugin  Créer un plugin vide"
	@echo ""
	@echo "  URLs"
	@echo "  ----"
	@echo "  WordPress  : http://localhost:8080"
	@echo "  Admin      : http://localhost:8080/wp-admin"
	@echo "  phpMyAdmin : http://localhost:8081"
	@echo "  Mailhog    : http://localhost:8025"
	@echo ""

# ---- Docker ----

up:
	docker compose up -d
	@echo ""
	@echo "  WordPress  : http://localhost:$$(grep WP_PORT .env | cut -d= -f2)"
	@echo "  Admin      : http://localhost:$$(grep WP_PORT .env | cut -d= -f2)/wp-admin"
	@echo "  phpMyAdmin : http://localhost:$$(grep PMA_PORT .env | cut -d= -f2)"
	@echo "  Mailhog    : http://localhost:$$(grep MAILHOG_PORT .env | cut -d= -f2)"

down:
	docker compose down

restart:
	docker compose restart wordpress

build:
	docker compose build --no-cache wordpress

logs:
	docker compose logs -f wordpress

shell:
	docker compose exec wordpress bash

# ---- WP-CLI ----
# Utilisation : make wp plugin list
wp:
	docker compose run --rm wpcli wp $(filter-out $@,$(MAKECMDGOALS))

%:
	@:

# ---- Installation WordPress ----

install:
	@echo "Installation de WordPress..."
	@docker compose run --rm wpcli wp core install \
		--url=$$(grep WP_URL .env | cut -d= -f2) \
		--title="$$(grep WP_TITLE .env | cut -d= -f2)" \
		--admin_user=$$(grep WP_ADMIN_USER .env | cut -d= -f2) \
		--admin_password=$$(grep WP_ADMIN_PASSWORD .env | cut -d= -f2) \
		--admin_email=$$(grep WP_ADMIN_EMAIL .env | cut -d= -f2) \
		--skip-email
	@echo ""
	@echo "  WordPress installé !"
	@echo "  Admin : http://localhost:$$(grep WP_PORT .env | cut -d= -f2)/wp-admin"
	@echo "  Login : $$(grep WP_ADMIN_USER .env | cut -d= -f2) / $$(grep WP_ADMIN_PASSWORD .env | cut -d= -f2)"

# ---- Reset complet ----

reset:
	@echo "Suppression de toutes les données..."
	docker compose down -v
	docker compose up -d
	@echo "Attente du démarrage de la base de données..."
	@sleep 10
	@$(MAKE) install

# ---- Création d'un thème ----
# Usage : make theme name=mon-theme

theme:
ifndef name
	@echo "Erreur : spécifie le nom du thème avec name=<nom>"
	@echo "Exemple : make theme name=mon-theme"
	@exit 1
endif
	@mkdir -p src/themes/$(name)
	@echo "<?php\n/*\n * Theme Name: $(name)\n * Description: Mon thème WordPress\n * Version: 1.0.0\n * Author: Arnaud\n */" > src/themes/$(name)/style.css
	@echo "<?php // Silence is golden." > src/themes/$(name)/functions.php
	@echo "<!DOCTYPE html><html <?php language_attributes(); ?>><head><meta charset=\"<?php bloginfo('charset'); ?>\"><title><?php wp_title(); ?></title><?php wp_head(); ?></head><body <?php body_class(); ?>><?php wp_footer(); ?></body></html>" > src/themes/$(name)/index.php
	@echo "  Thème '$(name)' créé dans src/themes/$(name)/"

# ---- Création d'un plugin ----
# Usage : make plugin name=mon-plugin

plugin:
ifndef name
	@echo "Erreur : spécifie le nom du plugin avec name=<nom>"
	@echo "Exemple : make plugin name=mon-plugin"
	@exit 1
endif
	@mkdir -p src/plugins/$(name)
	@printf "<?php\n/**\n * Plugin Name: $(name)\n * Description: Mon plugin WordPress\n * Version: 1.0.0\n * Author: Arnaud\n */\n\nif ( ! defined( 'ABSPATH' ) ) exit;\n" > src/plugins/$(name)/$(name).php
	@echo "  Plugin '$(name)' créé dans src/plugins/$(name)/"
