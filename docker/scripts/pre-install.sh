#!/bin/sh
echo "* Creating admin-dev symlink for PS 9.2 assets:install..."
ln -sfn /var/www/html/${PS_FOLDER_ADMIN:-admin-sharkness} /var/www/html/admin-dev
