<?php

namespace SNOWGIRL_SHOP;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Mysql\MysqlQuery;
use SNOWGIRL_SHOP\Catalog\SRC;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Entity\Stock;
use SNOWGIRL_SHOP\Http\HttpApp;
use SNOWGIRL_SHOP\Item\URI as ItemURI;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\Vendor;
use SNOWGIRL_CORE\Mysql\MysqlQueryExpression;
use SNOWGIRL_SHOP\View\Builder as Views;

/**
 * @todo    remove custom hit logs and parse web-server's hit log file...
 * Class Analytics
 * @property HttpApp|ConsoleApp app
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

    public function logGoHit(Entity $entity): bool
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

    public function logItemBuyHit(Item $item): bool
    {
        return $this->logHit(self::ITEM_BUY_HIT, implode(' ', [
            $item->getId(),
            $item->isInStock() ? 1 : 0
        ]));
    }

    public function logItemShopHit(Vendor $shop): bool
    {
        return $this->logHit(self::ITEM_SHOP_HIT, implode(' ', [
            $shop->getId()
        ]));
    }

    public function logItemStockHit(Stock $stock): bool
    {
        return $this->logHit(self::ITEM_STOCK_HIT, implode(' ', [
            $stock->getId()
        ]));
    }

    public function logItemPageHit(ItemURI $uri): bool
    {
        $item = $uri->getSRC()->getItem();

        return $this->logHit(self::ITEM_PAGE_HIT, implode(' ', [
            $item->getId(),
            $item->isInStock() ? 1 : 0
        ]));
    }

    public function logCatalogPageHit(URI $uri): bool
    {
        return $this->logHit(self::CATALOG_PAGE_HIT, json_encode($uri->getParams()));
    }

    public function updateRatings(): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $output = parent::updateRatings();

        $output = $output && $this->updateAttributesRatings();
        $output = $output && $this->updateItemsRatingsByPageHits();
        $output = $output && $this->updateItemsRatingsByBuyHits();
        $output = $output && $this->updateCache();

        return $output;
    }

    public function getItemRatingStarCost(): ?int
    {
        if ($this->app->container->memcache->has(self::CACHE_ITEM_RATING_START_COST, $output)) {
            return $output;
        }

        return null;
    }

    public function dropRatings(): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $output = parent::dropRatings();

        $output = $output && $this->dropItemsRatings();
        $output = $output && $this->updateCache();

        return $output;
    }

    private function updateItemsRatingsByBuyHits(): bool
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

    private function updateItemsRatingsByPageHits(): bool
    {
        $counts = [];

        $isOk = $this->walkFile(self::ITEM_PAGE_HIT, function ($tmp) use (&$counts) {
            $id = $tmp[0];
            $isInStock = 1 == $tmp[1];

            if ($isInStock) {
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

    private function updateAttributesRatings(): bool
    {
        $pkToCounts = [];

        /** @var Entity[] $pkToEntity */
        $pkToEntity = [];

        foreach ($this->app->managers->catalog->getComponentsOrderByDbKey() as $entity) {
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

    /** @todo improve
     *
     * @return bool
     */
    private function dropItemsRatings(): bool
    {
        $pk = $this->app->managers->items->getEntity()->getPk();
        $perGroup = 2 * SRC::getDefaultShowValue($this->app);

        $query = new MysqlQuery([
            'params' => [],
            'columns' => [$pk],
            'orders' => ['rating' => SORT_DESC]
        ]);

        $categoryIdToItems = $this->app->container->mysql->selectFromEachGroup(
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

    private function updateCache($quantile = 0.95)
    {
        $mysql = $this->app->managers->items->getMysql();

        $qr = $mysql->quote('rating');

        $row = $this->app->managers->items->clear()
            ->setColumns(new MysqlQueryExpression(implode(' ', [
                'SUBSTRING_INDEX(SUBSTRING_INDEX(GROUP_CONCAT(' . $qr . ' ORDER BY ' . $qr . ' SEPARATOR \',\'), \',\', ' . $quantile . ' * COUNT(*) + 1), \',\', -1)',
                'AS ' . $mysql->quote('quantile')
            ])))
            ->getArray();

        if ($row) {
            return $this->app->container->memcache->set(self::CACHE_ITEM_RATING_START_COST, ceil((float)$row['quantile'] / Views::ITEM_RATING_STAR_MAX));
        }

        return $this->app->container->memcache->delete(self::CACHE_ITEM_RATING_START_COST);
    }
}