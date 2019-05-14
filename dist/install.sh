#!/usr/bin/env bash

# Before run:
# 1) echo '{
#  "name": "snowgirl-app",
#  "autoload": {
#    "psr-4": {
#      "APP\\": "src"
#    }
#  },
#  "authors": [
#    {
#      "name": "alex.snowgirl",
#      "email": "alex.snowgirl@gmail.com"
#    }
#  ],
#  "prefer-stable": true,
#  "minimum-stability": "dev",
#  "require": {
#    "php": "^7.2",
#    "snowgirl/shop": "dev-master"
#  }
#}' >> ./composer.json

# 2) composer update

# 3) cp ./vendor/snowgirl/shop/dist/install.sh ./install.sh

# 4) sh install.sh

echo ""
echo "...structure"
echo ""

mkdir -p src/App
echo "<?php

namespace APP\App;

class Web extends \SNOWGIRL_SHOP\App\Web
{
}" >> ./src/App/Web.php

echo "<?php

namespace APP\App;

class Console extends \SNOWGIRL_SHOP\App\Console
{
}" >> ./src/App/Console.php

mkdir css
echo "/*xs*/
/*mb*/
@media (min-width: 480px) {}
/*sm*/
@media (min-width: 768px) {}
/*md*/
@media (min-width: 992px) {}
/*lg*/
@media (min-width: 1200px) {}" > ./css/core.css

mkdir js
echo "console.log('ok');" > ./js/core.js

mkdir trans
mkdir view

mkdir var
mkdir var/tmp
mkdir var/log
mkdir var/cache
echo "" > ./var/log/access.log
echo "" > ./var/log/error.log
echo "" > ./var/log/web.log
echo "" > ./var/log/web-outer.log
echo "" > ./var/log/web-admin.log
echo "" > ./var/log/console.log
echo "" > ./var/log/hit.page.log

mkdir public
cp ./vendor/snowgirl/core/dist/index.php ./public/index.php

mkdir public/css
ln -s ../../vendor/snowgirl/core/css public/css/core
ln -s ../../vendor/snowgirl/shop/css public/css/shop
ln -s ../../css public/css/app

mkdir public/js
ln -s ../../vendor/snowgirl/core/js public/js/core
ln -s ../../vendor/snowgirl/shop/js public/js/shop
ln -s ../../js public/js/app

mkdir -p public/img/0/0

mkdir bin
cp ./vendor/snowgirl/core/dist/console.php ./bin/console

cp ./vendor/snowgirl/shop/dist/config.ini ./config.ini

echo "" > ./info.txt


echo ""
echo "...config"
echo ""

read -p 'site: ' site
perl -pi -w -e "s/{site}/${site}/g;" ./config.ini

read -p 'domain: ' domain
perl -pi -w -e "s/{domain}/${domain}/g;" ./config.ini

read -p 'memcache_prefix: ' memcache_prefix
perl -pi -w -e "s/{memcache_prefix}/${memcache_prefix}/g;" ./config.ini

read -p 'elastic_prefix: ' elastic_prefix
perl -pi -w -e "s/{elastic_prefix}/${elastic_prefix}/g;" ./config.ini

echo ""
echo "...database"
echo ""

read -p 'db_root_user: ' db_root_user
read -p 'db_root_pass: ' db_root_pass

echo 'db_schema: '$domain
echo ""
db_schema=$domain
perl -pi -w -e "s/{db_schema}/${db_schema}/g;" ./config.ini

read -p 'db_user: ' db_user
perl -pi -w -e "s/{db_user}/${db_user}/g;" ./config.ini

read -p 'db_pass: ' db_pass
perl -pi -w -e "s/{db_pass}/${db_pass}/g;" ./config.ini

query="CREATE DATABASE \`${db_schema}\`;"
echo "..."$query
mysql -u${db_root_user} -p${db_root_pass} -e "${query}"

query="CREATE USER \`${db_user}\`@localhost IDENTIFIED BY '${db_pass}';"
echo "..."$query
mysql -u${db_root_user} -p${db_root_pass} -e "${query}"

query="GRANT ALL PRIVILEGES ON \`${db_schema}\`.* TO \`${db_user}\`@'localhost';"
echo "..."$query
mysql -u${db_root_user} -p${db_root_pass} -e "${query}"

query="FLUSH PRIVILEGES;"
echo "..."$query
mysql -u${db_root_user} -p${db_root_pass} -e "${query}"

mysql -u${db_root_user} -p${db_root_pass} ${db_schema} < ./vendor/snowgirl/core/dist/dump.sql
mysql -u${db_root_user} -p${db_root_pass} ${db_schema} < ./vendor/snowgirl/shop/dist/dump.sql

sudo chown -R www-data:www-data ./ && sudo chmod -R g+w ./