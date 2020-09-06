<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Helper\WalkChunk2;
use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_CORE\Sitemap as Generator;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Item\URI as ItemURI;

class Sitemap extends \SNOWGIRL_CORE\Util\Sitemap
{
    private $noIndexBrands;
    private $catalogUriPrefix;

    protected function initialize()
    {
        $this->noIndexBrands = $this->app->managers->brands->clear()
            ->setWhere(['no_index' => 1])
            ->getColumn('brand_id');

        $this->catalogUriPrefix = URI::addUriPrefix() ? (URI::CATALOG . '/') : '';

        return parent::initialize();
    }

    protected function getGenerators()
    {
        $output = array_merge(parent::getGenerators(), [
            'catalog_custom' => $this->getCatalogCustomGenerator(),
            'catalog' => $this->getCatalogGenerator(),
            'items' => $this->getItemsGenerator()
        ]);

        if (isset($output['pages'])) {
            unset($output['pages']);
        }

        return $output;
    }

    protected function getCoreGenerator()
    {
        return function (Generator $sitemap) {
            //@todo move to page_regular as separate column...
            $priorityMap = [
                'index' => '1.0',
                'brands' => '1.0',
                'vendors' => '0.1',
                'contacts' => '0.1',
                'stock' => '1.0'
            ];

            $pages = $this->app->managers->pages;

            foreach ($pages->getMenu() as $key => $page) {
                $sitemap->add($pages->getLink($page, [], false), $priorityMap[$key] ?? '1.0', 'weekly');
            }

            $sitemap->add('/' . URI::CATALOG, '1.0', 'daily');
        };
    }

    protected function getCatalogCustomGenerator()
    {
        return function (Generator $sitemap) {
            $app = $this->app;

            $catalog = $app->managers->catalog->clear();
            $customCatalog = $app->managers->catalogCustom->clear();

            $db = $app->managers->catalogCustom->getDb();

            $pk = $customCatalog->getEntity()->getPk();

            (new WalkChunk2(1000))
                ->setFnGet(function ($lastId, $size) use ($db, $pk, $catalog, $customCatalog) {
                    if ($lastId) {
                        $customCatalog->setWhere(new Expression($db->quote($pk) . ' > ?', $lastId));
                    }

                    $customs = $customCatalog
                        ->setColumns([$pk, 'params_hash', 'created_at', 'updated_at'])
                        ->setOrders([$pk => SORT_ASC])
                        ->setLimit($size)
                        ->getArrays('params_hash');

                    $catalogs = $catalog
                        ->setColumns(['params_hash', 'uri'])
                        ->setWhere(['params_hash' => array_keys($customs)])
                        ->getArrays('params_hash');


                    $customs = array_filter($customs, function ($custom) use ($catalogs) {
                        return isset($catalogs[$custom['params_hash']]);
                    });

                    return array_map(function ($custom) use ($catalogs) {
                        $custom['uri'] = $catalogs[$custom['params_hash']]['uri'];
                        return $custom;
                    }, $customs);
                })
                ->setFnDo(function ($items) use ($sitemap, $pk) {
                    foreach ($items as $item) {
                        $sitemap->add('/' . $this->catalogUriPrefix . $item['uri'], '1.0', 'weekly',
                            $this->getAddLastModParamByTimes($item['updated_at'], $item['created_at']));
                    }

                    return ($last = array_pop($items)) ? $last[$pk] : false;
                })
                ->run();
        };
    }

    /**
     * Alternative method - much more effective (using [is_article, count] Db key and last-id instead of limit's
     * offsets)
     * @return \Closure
     */
    protected function getCatalogGenerator()
    {
        return function (Generator $sitemap) {
            $app = $this->app;
            $db = $app->container->db;

            $catalog = $app->managers->catalog->clear();

            $pk = $catalog->getEntity()->getPk();

            $now = date('c');

            (new WalkChunk2(1000))
                ->setFnGet(function ($lastId, $size) use ($db, $catalog, $pk) {
                    if ($lastId) {
                        $catalog->setWhere(new Expression($db->quote($pk) . ' > ?', $lastId));
                    }

                    return $catalog
                        ->setColumns([$pk, 'uri'])
                        ->setOrders([$pk => SORT_ASC])
                        ->setLimit($size)
                        ->getArrays();
                })
                ->setFnDo(function ($items) use ($sitemap, $pk, $now) {
                    foreach ($items as $item) {
                        $sitemap->add('/' . $this->catalogUriPrefix . $item['uri'], '0.9', 'weekly', $now);
                    }

                    return ($last = array_pop($items)) ? $last[$pk] : false;
                })
                ->run();
        };
    }

    protected function getItemsGenerator()
    {
        return function (Generator $sitemap) {
            $app = $this->app;
            $db = $app->container->db;
            $items = $app->managers->items->clear();
            $pk = $items->getEntity()->getPk();

            $where = [];

            if ($app->configMasterOrOwn('catalog.in_stock_only', false)) {
                $where['is_in_stock'] = 1;
            }

            (new WalkChunk2(1000))
                ->setFnGet(function ($lastId, $size) use ($db, $items, $pk, $where) {
                    if ($lastId) {
                        $where[] = new Expression($db->quote($pk) . ' > ?', $lastId);
                    }

                    return $items
                        ->setColumns([$pk, 'name', 'image', 'is_in_stock', 'brand_id', 'created_at', 'updated_at'])
                        ->setWhere($where)
                        ->setOrders([$pk => SORT_ASC])
                        ->setLimit($size)
                        ->getArrays();
                })
                ->setFnDo(function ($items) use ($sitemap, $pk) {
                    foreach ($items as $item) {
                        if (!in_array($item['brand_id'], $this->noIndexBrands)) {
                            $sitemap->add(
                                '/' . $this->catalogUriPrefix . ItemURI::buildPath($item['name'], $item[$pk]),
                                (1 == $item['is_in_stock']) ? '0.8' : '0.5',
                                'weekly',
                                $this->getAddLastModParamByTimes($item['updated_at'], $item['created_at']),
                                $this->getAddImageParam($item['image'], $item['name'])
                            );
                        }
                    }

                    return ($last = array_pop($items)) ? $last[$pk] : false;
                })
                ->run();
        };
    }
}