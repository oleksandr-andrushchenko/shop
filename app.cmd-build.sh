#!/usr/bin/env bash

# !!!should be placed into  project's root folder

echo ""
echo "...project"
echo ""

composer init

echo ""
echo "...dependencies"
echo ""

cp ./vendor/snowgirl-shop/app.composer.json ./composer.json

composer update

echo ""
echo "...structure"
echo ""

mkdir app
mkdir app/core
mkdir app/css
echo "/*xs*/
/*mb*/
@media (min-width: 480px) {}
/*sm*/
@media (min-width: 768px) {}
/*md*/
@media (min-width: 992px) {}
/*lg*/
@media (min-width: 1200px) {}" > ./app/css/core.css
mkdir app/js
mkdir app/locale
mkdir app/view

mkdir tmp
mkdir logs
echo "" > ./logs/access.log
echo "" > ./logs/admin.log
echo "" > ./logs/hit.catalog.log
echo "" > ./logs/hit.page.log
echo "" > ./logs/open-door.log
echo "" > ./logs/server.log
echo "" > ./logs/web.log

mkdir web
cp ./vendor/snowgirl-shop/app.index.php ./web/index.php
mkdir web/css
ln -s ../../vendor/snowgirl-core/css web/css/snowgirl-core
ln -s ../../vendor/snowgirl-shop/css web/css/snowgirl-shop
ln -s ../../app/css web/css/app
mkdir web/js
ln -s ../../vendor/snowgirl-core/js web/js/snowgirl-core
ln -s ../../vendor/snowgirl-shop/js web/js/snowgirl-shop
ln -s ../../app/js web/js/app
mkdir -p web/img/0/0

cp ./vendor/snowgirl-shop/app.cmd2.php ./web/cmd
cp ./vendor/snowgirl-shop/app.config.ini ./config.ini
echo "" > ./info.txt

echo ""
echo "...config"
echo ""

read -p 'domain: ' domain
read -p 'site: ' site
perl -pi -w -e "s/{site}/${site}/g;" ./config.ini
read -p 'domain: ' domain
perl -pi -w -e "s/{domain}/${domain}/g;" ./config.ini
read -p 'memcache_prefix: ' memcache_prefix
perl -pi -w -e "s/{memcache_prefix}/${memcache_prefix}/g;" ./config.ini
read -p 'sphinx_prefix: ' sphinx_prefix
perl -pi -w -e "s/{sphinx_prefix}/${sphinx_prefix}/g;" ./config.ini

echo ""
echo "...database"
echo ""

read -p 'db_root_user: ' db_root_user
read -p 'db_root_pass: ' db_root_pass

echo 'db_schema: '$domain
echo ""
db_schema=$domain
perl -pi -w -e "s/{db_schema}/${db_schema}/g;" ./config.ini
echo 'db_user: '$domain
echo ""
db_user=$domain
perl -pi -w -e "s/{db_user}/${db_user}/g;" ./config.ini
read -p 'db_pass: ' db_pass
perl -pi -w -e "s/{db_pass}/${db_pass}/g;" ./config.ini
read -p 'db_source: ' db_source

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

mysqldump -u${db_root_user} -p${db_root_pass} -d ${db_source} | sed "s/ AUTO_INCREMENT=[0-9]*\b//" | mysql -u${db_root_user} -p${db_root_pass} -D ${db_schema}

query="INSERT INTO \`${db_schema}\`.\`user\`(\`login\`, \`password\`, \`role\`) VALUES('alex.snowgirl', '8c516acb240c87080e55e8bf3bc1bd28', 3);"
echo "..."$query
mysql -u${db_root_user} -p${db_root_pass} -e "${query}"

ln -s ./vendor/lox/xhprof/xhprof_html/ profiler

sudo chown -R www-data:www-data ./ && sudo chmod -R g+w ./