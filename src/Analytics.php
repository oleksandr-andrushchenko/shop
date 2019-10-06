<?php

namespace SNOWGIRL_SHOP;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_SHOP\Catalog\SRC;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Stock;
use SNOWGIRL_SHOP\Item\URI as ItemURI;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\Vendor;
use SNOWGIRL_SHOP\App\Web;
use SNOWGIRL_SHOP\App\Console;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_SHOP\View\Builder as Views;

/**
 * @todo    remove custom hit logs and parse web-server's hit log file...
 * Class Analytics
 * @property Web|Console app
 * @package SNOWGIRL_SHOP
 */
class Analytics extends \SNOWGIRL_CORE\Analytics
{
    private const CATALOG_PAGE_HIT = 'hit.catalog';
    private const ITEM_PAGE_HIT = 'hit.item';
    private const ITEM_BUY_HIT = 'hit.buy';
    private const ITEM_SHOP_HIT = 'hit.shop';
    private const ITEM_STOCK_HIT = 'hit.stock';

    private const CACHE_ITEM_RATING_START_COST = 'item_rating_star_cost';

    public function logGoHit(Entity $entity)
    {
        switch (get_class($entity)) {
            case Item::class:
                /** @var Item $entity */
                return $this->logItemBuyHit($entity);
            case Vendor::class:
                /** @var Vendor $entity */
                return $this->logItemShopHit($entity);
            case Stock::class:
                /** @var Stock $entity */
                return $this->logItemStockHit($entity);
            default:
                return false;
        }
    }

    /**
     * @param Item $item
     *
     * @return bool
     */
    public function logItemBuyHit(Item $item)
    {
        $this->logHit(self::ITEM_BUY_HIT, implode(' ', [
            $item->getId(),
            $item->isInStock() ? 1 : 0
        ]));

        return true;
    }

    public function logItemShopHit(Vendor $shop)
    {
        $this->logHit(self::ITEM_SHOP_HIT, implode(' ', [
            $shop->getId()
        ]));

        return true;
    }

    public function logItemStockHit(Stock $stock)
    {
        $this->logHit(self::ITEM_STOCK_HIT, implode(' ', [
            $stock->getId()
        ]));

        return true;
    }

    protected function updateItemsRatingsByBuyHits()
    {
        $counts = [];

        $isOk = $this->walkFile(self::ITEM_BUY_HIT, function ($tmp) use (&$counts) {
            $id = $tmp[0];

            if (!isset($counts[$id])) {
                $counts[$id] = 0;
            }

            $counts[$id]++;
        });

        if (!$isOk) {
            return false;
        }

        $this->updateRatingsByEntity(Item::class, $counts);

        return true;
    }

    public function logItemPageHit(ItemURI $uri)
    {
        $item = $uri->getSRC()->getItem();

        $this->logHit(self::ITEM_PAGE_HIT, implode(' ', [
            $item->getId(),
            $item->get('archive') ? 0 : 1,
            $item->isInStock() ? 1 : 0
        ]));
    }

    /**
     * @return bool
     */
    protected function updateItemsRatingsByPageHits()
    {
        $counts = [];

        $isOk = $this->walkFile(self::ITEM_PAGE_HIT, function ($tmp) use (&$counts) {
            $id = $tmp[0];
            $isActive = 1 == $tmp[1];
            $isInStock = 1 == $tmp[2];

            if ($isActive && $isInStock) {
                if (!isset($counts[$id])) {
                    $counts[$id] = 0;
                }

                $counts[$id]++;
            }
        });

        if (!$isOk) {
            return false;
        }

        $this->updateRatingsByEntity(Item::class, $counts);

        return true;
    }

    public function logCatalogPageHit(URI $uri)
    {
        $this->logHit(self::CATALOG_PAGE_HIT, json_encode($uri->getParams()));
    }

    protected function updateAttributesRatings()
    {
        $pkToCounts = [];

        /** @var Entity[] $pkToEntity */
        $pkToEntity = [];

        foreach ($this->app->managers->catalog->getComponentsOrderByRdbmsKey() as $entity) {
            if (array_key_exists('rating', $entity::getColumns())) {
                $pkToCounts[$entity::getPk()] = [];
                $pkToEntity[$entity::getPk()] = $entity;
            }
        }

        $isOk = $this->walkFile(self::CATALOG_PAGE_HIT, function ($tmp) use (&$pkToCounts) {
            $jsonParams = $tmp[0];

            if ($params = json_decode($jsonParams, true)) {
                foreach ($params as $pk => $ids) {
                    if (!is_array($ids)) {
                        $ids = [$ids];
                    }

                    foreach ($ids as $id) {
                        if (!isset($pkToCounts[$pk])) {
                            continue;
                        }

                        if (!isset($pkToCounts[$pk][$id])) {
                            $pkToCounts[$pk][$id] = 0;
                        }

                        $pkToCounts[$pk][$id]++;
                    }
                }
            }
        });

        if (!$isOk) {
            return false;
        }

        foreach ($pkToCounts as $pk => $counts) {
            $this->updateRatingsByEntity($pkToEntity[$pk], $counts);
        }

        return true;
    }

    public function updateRatings()
    {
        $output = parent::updateRatings();

        $output = $output && $this->updateAttributesRatings();
        $output = $output && $this->updateItemsRatingsByPageHits();
        $output = $output && $this->updateItemsRatingsByBuyHits();
        $output = $output && $this->updateCache();

        return $output;
    }

    /** @todo improve
     *
     * @return bool
     */
    protected function dropItemsRatings()
    {
        $pk = $this->app->managers->items->getEntity()->getPk();
        $perGroup = 2 * SRC::getDefaultShowValue($this->app);

        $query = new Query([
            'params' => [],
            'columns' => [$pk],
            'orders' => ['rating' => SORT_DESC]
        ]);

        $categoryIdToItems = $this->app->services->rdbms->selectFromEachGroup(
            $this->app->managers->items->getEntity()->getTable(),
            $this->app->managers->categories->getEntity()->getPk(),
            $perGroup,
            $query
        );

        $map = [
            16 => 3,
            32 => 2,
            $perGroup => 1
        ];

        $keys = array_keys($map);

        $counts = [];

        foreach ($categoryIdToItems as $categoryId => $items) {
            foreach ($items as $i => $item) {
                $rating = 1;

                for ($j = 0, $s = count($keys); $j < $s; $j++) {
                    $start = $keys[$j - 1] ?? 0;
                    $end = $keys[$j];

                    if ($i >= $start && $i < $end) {
                        $rating = $map[$end];
                    }
                }

                $counts[$item[$pk]] = $rating;
            }
        }

        $this->app->managers->items->updateMany(['rating' => 0]);

        return $this->updateRatingsByEntity(Item::class, $counts, false);
    }

    protected function updateCache($quantile = 0.95)
    {
        $storage = $this->app->managers->items->getStorage();


        $qr = $storage->quote('rating');

        $row = $this->app->managers->items->clear()
            ->setColumns(new Expr(implode(' ', [
                'SUBSTRING_INDEX(SUBSTRING_INDEX(GROUP_CONCAT(' . $qr . ' ORDER BY ' . $qr . ' SEPARATOR \',\'), \',\', ' . $quantile . ' * COUNT(*) + 1), \',\', -1)',
                'AS ' . $storage->quote('quantile')
            ])))
            ->getArray();

        if ($row) {
            return $this->app->managers->cache->set(self::CACHE_ITEM_RATING_START_COST, ceil((float)$row['quantile'] / Views::ITEM_RATING_STAR_MAX));
        }

        return $this->app->managers->cache->delete(self::CACHE_ITEM_RATING_START_COST);
    }

    public function getItemRatingStarCost()
    {
        return $this->app->managers->cache->get(self::CACHE_ITEM_RATING_START_COST);
    }

    public function dropRatings()
    {
        $output = parent::dropRatings();

        $output = $output && $this->dropItemsRatings();
        $output = $output && $this->updateCache();

        return $output;
    }
}