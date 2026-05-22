FROM wordpress:6.5-php8.2-apache

# Outils système utiles pour le développement
RUN apt-get update && apt-get install -y \
    less \
    vim \
    git \
    curl \
    unzip \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Composer (gestionnaire de dépendances PHP)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# WP-CLI
RUN curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Config PHP pour le développement (erreurs visibles, logs activés)
COPY .docker/php/dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

# Entrypoint qui aligne l'uid www-data avec l'utilisateur hôte
COPY .docker/entrypoint.sh /usr/local/bin/dev-entrypoint.sh
RUN chmod +x /usr/local/bin/dev-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/dev-entrypoint.sh"]

# Répertoire de travail WordPress
WORKDIR /var/www/html
