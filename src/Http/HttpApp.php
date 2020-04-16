<?php

namespace SNOWGIRL_SHOP\Http;

use SNOWGIRL_CORE\AbstractApp;
use SNOWGIRL_CORE\Http\Route;
use SNOWGIRL_CORE\Http\Router;
use SNOWGIRL_CORE\Helper\Arrays;
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
class HttpApp extends \SNOWGIRL_CORE\Http\HttpApp
{
    protected function register()
    {
        parent::register();

        \SNOWGIRL_SHOP\Catalog\URI::setApp($this);
        \SNOWGIRL_SHOP\Item\URI::setApp($this);
    }

    protected function addMaps($root): AbstractApp
    {
        parent::addMaps($root);

        $this->dirs['@shop'] = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
        $this->namespaces['@shop'] = 'SNOWGIRL_SHOP';

        $this->namespaces = Arrays::sortByKeysArray($this->namespaces, [
            '@app',
            '@shop',
            '@core'
        ]);

        return $this;
    }

    protected function addRoutes(Router $router): AbstractApp
    {
        $router->addRoute('item', new Route((URI::addUriPrefix() ? (URI::CATALOG . '/') : '') . ':uri', [
            'controller' => 'outer',
            'action' => 'item'
        ], [
            'uri' => '.*-[0-9]+'
        ]));

        return $this;
    }

    protected function addFakeRoutes(Router $router): AbstractApp
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