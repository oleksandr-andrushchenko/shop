<?php

namespace SNOWGIRL_SHOP\Console;

class ConsoleApp extends \SNOWGIRL_CORE\Console\ConsoleApp
{
    protected function register()
    {
        \SNOWGIRL_SHOP\Catalog\URI::setApp($this);
        \SNOWGIRL_SHOP\Item\URI::setApp($this);
    }

    protected function addMaps($root)
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