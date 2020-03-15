<?php

namespace SNOWGIRL_SHOP\Console;

use SNOWGIRL_CORE\AbstractApp;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Http\Route;
use SNOWGIRL_CORE\Http\Router;
use SNOWGIRL_SHOP\Analytics;
use SNOWGIRL_SHOP\Catalog\URI;

use SNOWGIRL_SHOP\Manager\Builder as Managers;
use SNOWGIRL_SHOP\SEO;
use SNOWGIRL_SHOP\View\Builder as Views;
use SNOWGIRL_SHOP\Util\Builder as Utils;

/**
 * Class App
 *
 * @property Analytics analytics
 * @property Views views
 * @property Managers managers
 * @property Utils utils
 * @property SEO seo
 * @package SNOWGIRL_SHOP
 */
class ConsoleApp extends \SNOWGIRL_CORE\Console\ConsoleApp
{
    protected function register()
    {
        \SNOWGIRL_SHOP\Catalog\URI::setApp($this);
        \SNOWGIRL_SHOP\Item\URI::setApp($this);
    }

    protected function addMaps($root): AbstractApp
    {
        parent::addMaps($root);

        $this->dirs['@shop'] = dirname(__DIR__);
        $this->namespaces['@shop'] = 'SNOWGIRL_SHOP';

        $this->namespaces = Arrays::sortByKeysArray($this->namespaces, [
            '@app',
            '@shop',
            '@core'
        ]);

        return $this;
    }

    protected function addRoutes(Router $router)
    {
        $router->addRoute('item', new Route((URI::addUriPrefix() ? (URI::CATALOG . '/') : '') . ':uri', [
            'controller' => 'outer',
            'action' => 'item'
        ], [
            'uri' => '.*-[0-9]+'
        ]));

        return $this;
    }

    protected function addFakeRoutes(Router $router)
    {
        $route = ':uri';
        $defaults = [
            'controller' => 'outer',
            'action' => URI::CATALOG
        ];

        if (URI::addUriPrefix()) {
            $route = URI::CATALOG . '/' . $route;
            $defaults['uri'] = URI::CATALOG;
        }

        $router->addRoute('catalog', new Route($route, $defaults));

        return $this;
    }
}