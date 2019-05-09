<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 11/3/16
 * Time: 3:42 AM
 */

namespace SNOWGIRL_SHOP;

use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Route;
use SNOWGIRL_CORE\Router;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Builder as Managers;
use SNOWGIRL_SHOP\View\Builder as Views;
use SNOWGIRL_SHOP\Util\Builder as Utils;

/**
 * Class App
 * @property Analytics analytics
 * @property Views views
 * @property Managers managers
 * @property Utils utils
 * @property SEO seo
 * @package SNOWGIRL_SHOP
 */
class App extends \SNOWGIRL_CORE\App
{
    protected function addMaps($root)
    {
        parent::addMaps($root);

        $this->dirs['@snowgirl-shop'] = dirname(__DIR__);
        $this->namespaces['@snowgirl-shop'] = 'SNOWGIRL_SHOP';

        $this->namespaces = Arrays::sortByKeysArray($this->namespaces, [
            '@app',
            '@snowgirl-shop',
            '@snowgirl-core'
        ]);

        return $this;
    }

    protected function addRoutes(Router $router)
    {
        $router->addRoute('item', new Route((URI::addUriPrefix() ? (URI::CATALOG . '/') : '') . ':uri', [
            'controller' => 'openDoor',
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
            'controller' => 'openDoor',
            'action' => URI::CATALOG
        ];

        if (URI::addUriPrefix()) {
            $route = URI::CATALOG . '/' . $route;
            $defaults['uri'] = URI::CATALOG;
        }

        $router->addRoute('catalog', new Route($route, $defaults));

        return $this;
    }

    public function findClass($rawClass)
    {
        $tmp = 'APP\\' . $rawClass;

        if ($this->loader->findFile($tmp)) {
            return $tmp;
        }

        $tmp = 'SNOWGIRL_SHOP\\' . $rawClass;

        if ($this->loader->findFile($tmp)) {
            return $tmp;
        }

        return 'SNOWGIRL_CORE\\' . $rawClass;
    }
}