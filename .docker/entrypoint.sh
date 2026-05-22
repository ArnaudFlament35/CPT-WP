#!/bin/bash
# Aligne l'uid/gid de www-data avec celui de l'utilisateur hôte.
if [ -n "$LOCAL_UID" ] && [ "$LOCAL_UID" != "33" ]; then
    usermod -u "$LOCAL_UID" www-data
    groupmod -g "${LOCAL_GID:-$LOCAL_UID}" www-data
    find /var/www/html -user 33 -exec chown www-data:www-data {} + 2>/dev/null || true
fi

# Crée le dossier de logs PHP et le rend accessible
mkdir -p /var/log/php
touch /var/log/php/error.log
chown -R www-data:www-data /var/log/php

exec docker-entrypoint.sh apache2-foreground
