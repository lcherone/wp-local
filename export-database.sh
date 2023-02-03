#!/bin/bash

cd public

# copy local wp-config.php to public
if [ ! -e wp-cli.phar ]
then
    wget https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
fi

php wp-cli.phar db export --add-drop-table --allow-root ../sql/local.sql

rm wp-cli.phar
