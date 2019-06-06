#!/usr/bin/env bash

#1) prepare config.ini
#2) prepare web-server virtual host

echo '{
  "name": "snowgirl/shop-app",
  "autoload": {
    "psr-4": {
      "APP\\": "src"
    }
  },
  "authors": [
    {
      "name": "alex.snowgirl",
      "email": "alex.snowgirl@gmail.com"
    }
  ],
  "prefer-stable": true,
  "minimum-stability": "dev",
  "require": {
    "php": "^7.2",
    "snowgirl/shop": "dev-master"
  }
}
' >> ./composer.json

mv -v ./app/* ./
rm -rf ./app
mv ./core ./src
mv ./web ./public

mkdir ./src/App

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

echo "/*xs*/
/*mb*/
@media (min-width: 480px) {}
/*sm*/
@media (min-width: 768px) {}
/*md*/
@media (min-width: 992px) {}
/*lg*/
@media (min-width: 1200px) {}" > ./css/core.css

echo "console.log('ok');" > ./js/core.js

mv ./locale ./trans

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

echo "" > ./var/log/hit.buy.log
echo "" > ./var/log/hit.catalog.log
echo "" > ./var/log/hit.item.log
echo "" > ./var/log/hit.stock.log
echo "" > ./var/log/uri.log

rm -rf ./logs
rm -rf ./tmp



cp ./vendor/snowgirl/core/dist/index.php ./public/index.php

rm ./public/css/snowgirl-core
ln -s ../../vendor/snowgirl/core/css public/css/core
rm ./public/css/snowgirl-shop
ln -s ../../vendor/snowgirl/shop/css public/css/shop
rm ./public/css/app
ln -s ../../css public/css/app

rm ./public/js/snowgirl-core
ln -s ../../vendor/snowgirl/core/js public/js/core
rm ./public/js/snowgirl-shop
ln -s ../../vendor/snowgirl/shop/js public/js/shop
rm ./public/js/app
ln -s ../../js public/js/app

mkdir bin
cp ./vendor/snowgirl/core/dist/console.php ./bin/console

#sudo chown -R www-data:www-data ./ && sudo chmod -R g+w ./