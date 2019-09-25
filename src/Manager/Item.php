<?php

namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_CORE\App;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_SHOP\Catalog\SRC;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Import\Source;
use SNOWGIRL_SHOP\Item\URI as ItemURI;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttr;
use SNOWGIRL_SHOP\Manager\Item\DataProvider;
use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalogManager;

use SNOWGIRL_SHOP\Entity\Item as ItemEntity;
use SNOWGIRL_SHOP\Entity\Item\Archive as ItemArchiveEntity;
use SNOWGIRL_SHOP\Entity\Tag as TagEntity;
use SNOWGIRL_SHOP\Entity\Material as MaterialEntity;
use SNOWGIRL_SHOP\Entity\Brand as BrandEntity;
use SNOWGIRL_SHOP\Entity\Category as CategoryEntity;
use SNOWGIRL_SHOP\Entity\Color as ColorEntity;
use SNOWGIRL_SHOP\Entity\Country as CountryEntity;
use SNOWGIRL_SHOP\Entity\Vendor as VendorEntity;
use SNOWGIRL_CORE\Service\Storage\Query;

/**
 * @todo    split into Item\SRC and Item\URI (add alias URIs then after...)
 * Class Item
 * @property App        app
 * @property ItemEntity $entity
 * @method Item clear()
 * @method ItemEntity find($id)
 * @method Item copy($clear = false)
 * @method DataProvider getDataProvider()
 * @method ItemEntity[] getObjects($idAsKeyOrKey = null)
 * @package SNOWGIRL_SHOP\Manager
 */
class Item extends Manager implements GoLinkBuilderInterface
{
    public function onDeleted(Entity $entity)
    {
        /** @var ItemEntity $entity */

        $output = parent::onDeleted($entity);
        $output = $output && $this->app->images->get($entity->getImage())->delete();
        return $output;
    }

    /**
     * @param ItemEntity $item
     *
     * @return URI[]
     */
    public function getTagsURI(ItemEntity $item)
    {
        $output = [];

        $combinations = [
            [CategoryEntity::class],
            [CategoryEntity::class, URI::SPORT],
            [CategoryEntity::class, URI::SIZE_PLUS],
            [CategoryEntity::class, URI::SALES],
            [CategoryEntity::class, BrandEntity::class],
            [CategoryEntity::class, BrandEntity::class, URI::SALES],
            [CategoryEntity::class, TagEntity::class, BrandEntity::class],
            [CategoryEntity::class, TagEntity::class, ColorEntity::class],
            [CategoryEntity::class, TagEntity::class],
            [CategoryEntity::class, MaterialEntity::class],
            [CategoryEntity::class, ColorEntity::class],
            [BrandEntity::class]
        ];

        $components = PageCatalogManager::getComponentsOrderByRdbmsKey();
        $types = URI::TYPE_PARAMS;

        $limit = 7;

        //@todo fetch only those in combinations...
        $mva = $this->getMva($item, [], true);

        foreach ($combinations as $combination) {
            /** @var ItemAttr[] $combination */
            $pks = [];

            foreach ($combination as $component) {
                if (in_array($component, $components)) {
                    $pks[] = $component::getPk();
                }
            }

            $params = [];

            //sva
            foreach ($item->getAttrs() as $k => $v) {
                if (in_array($k, $pks) && $v) {
                    $params[$k] = $v;
                }
            }

            //mva
            foreach ($mva as $k => $v) {
                if (in_array($k, $pks) && $v) {
                    $params[$k] = is_array($v) && 1 == count($v) ? $v[0] : $v;
                }
            }

            //types
            foreach ($types as $type) {
                if (in_array($type, $combination)) {
                    if (URI::SALES == $type) {
                        if (0 < $item->getOldPrice()) {
                            $params[$type] = 1;
                        }
                    } else {
                        if (1 == $item->get($type)) {
                            $params[$type] = 1;
                        }
                    }
                }
            }

            if (count($combination) == count($params)) {
                $uri = new URI($params);

                $uri->getSEO()->retrieveAll(false);

                $output[] = $uri;

                if ($limit == count($output)) {
                    break;
                }
            }
        }

        return $output;
    }

    protected function getProviderClasses(): array
    {
        return [
            self::class
        ];
    }

    /**
     * @param ItemEntity $item
     *
     * @return Item[]
     */
    public function getImages(ItemEntity $item)
    {
        $output = [];

        $image = $item->getImage();
        $output[] = $image;

        if ($count = $item->getImageCount()) {
            $part = substr($image, 0, strlen($image) - 1);

            for ($i = 1; $i < $count; $i++) {
                $newImage = $part . $i;

                if ($newImage == $image) {
                    $newImage = $part . ++$count;
                }

                $output[] = $newImage;
            }
        }

        foreach ($output as $k => $v) {
            $output[$k] = $this->app->images->get($v);
        }

        return $output;
    }

    /**
     * @todo fix.... is_sport = 0 and is_size_plus = 0 and ordering...
     * @todo ...[https://example.com/platya-begood-3113847] SELECT  `item`.`item_id`, `brand_id` FROM `item` USE
     *       INDEX(`ix_order_desc_rating`)  WHERE `is_active` = 1 AND `is_sport` = 0 AND `is_size_plus` = 0 AND
     *       `category_id` = 413 AND `brand_id` = 3759  ORDER BY `order_desc_rating` ASC LIMIT 0, 40;
     * @todo ...[https://example.com/dublenka-lost-ink-57916866] SELECT  `item`.`item_id`, `brand_id` FROM `item`
     *       USE INDEX(`ix_order_desc_rating`)  WHERE `is_active` = 1 AND `is_sport` = 0 AND `is_size_plus` = 0 AND
     *       `category_id` = 1228 AND `brand_id` = 298  ORDER BY `order_desc_rating` ASC LIMIT 0, 40;
     *
     * @param ItemEntity $item
     *
     * @return URI
     */
    public function getRelatedCatalogURI(ItemEntity $item)
    {
        //@todo index of [is_active, is_sport, is_size_plus, category_id, brand_id] should exists and index forcing should be disabled!
        //@todo if changed - update index...

        $params = [];

        if ($item->isSport()) {
            $params[URI::SPORT] = 1;
        }

        if ($item->isSizePlus()) {
            $params[URI::SIZE_PLUS] = 1;
        }

        $params['category_id'] = $item->getCategoryId();

        if ($this->app->request->isWeAreReferer($referer)) {
            if (false !== strpos($referer, $this->getBrand($item)->getUri())) {
                $params['brand_id'] = $item->getBrandId();
            }

            foreach ($this->getMva($item) as $attrEntity => $attrId) {
                $manager = $this->app->managers->getByEntityClass($attrEntity);
                $attrPk = $manager->getEntity()->getPk();

                foreach ($attrId as $attrId2) {
                    if ($attrEntity = $manager->find($attrId2)) {
                        if (false !== strpos($referer, $attrEntity->get('uri'))) {
                            if (!isset($params[$attrPk])) {
                                $params[$attrPk] = [];
                            }

                            $params[$attrPk][] = $attrId2;
                        }
                    }
                }
            }
        }

        $params[URI::PRICE_FROM] = (int)($item->getPrice() * .8);
        $params[URI::PRICE_TO] = (int)($item->getPrice() * 1.2);

        return new URI($params);
    }

    protected static function getMvaCacheKey(ItemEntity $item, array $attrs)
    {
        return $item->getTable() . '-mva-' . md5($item->getId() . json_encode($attrs));
    }

    /**
     * @param ItemEntity $item
     * @param array      $attrs
     * @param bool|false $keysAsKeys
     *
     * @return array - [attrEntity => attrId[]]
     */
    public function getMva(ItemEntity $item, array $attrs = [], $keysAsKeys = false)
    {
        /** @var array $mva */
        $mva = Manager::mapEntitiesAddPksAsKeys(PageCatalogManager::getMvaComponents());

        if ($attrs) {
            $mva = array_filter($mva, function ($attr) use ($attrs) {
                return in_array($attr, $attrs);
            }, ARRAY_FILTER_USE_KEY);
        }

        if ($item->get('archive')) {
            $output = array_filter($item->getVars(), function ($attr) use ($mva) {
                return isset($mva[$attr]);
            }, ARRAY_FILTER_USE_KEY);
        } else {
            $key = $this->getMvaCacheKey($item, array_keys($mva));

            $output = $this->getCacheObject()->call($key, function () use ($item, $mva) {
                $pk = $this->entity->getPk();
                $table = $this->entity->getTable();

                $db = $this->app->services->rdbms(null, null, $this->masterServices);

                $columns = [];
                $joins = [];
                $where = [new Expr($db->quote($pk, $table) . ' = ?', $item->getId())];

                foreach ($mva as $attrPk => $attrEntity) {
                    /** @var Entity $attrEntity */

                    /** @var Entity $entity */
                    $table2 = 'item_' . $attrEntity::getTable();
                    $joins[] = 'LEFT JOIN ' . $db->quote($table2) . ' ON ' . $db->quote($pk, $table) . ' = ' . $db->quote('item_id', $table2);
                    $columns[] = new Expr('GROUP_CONCAT(DISTINCT ' . $db->quote($attrPk) . ') AS ' . $db->quote($attrPk));
                }

                $query = new Query(['params' => []]);
                $query->text = implode(' ', [
                    $db->makeSelectSQL($columns, false, $query->params),
                    $db->makeFromSQL($table),
                    implode(' ', $joins),
                    $db->makeWhereSQL($where, $query->params)
                ]);

                $output = $db->req($query)->reqToArray();

                return $output;
            });
        }

        $tmp = array_map(function ($attrId) {
            if ($attrId) {
                return array_map(function ($attrId) {
                    return (int)$attrId;
                }, explode(',', $attrId));
            }

            return [];
        }, $output);

        $output = [];

        foreach ($tmp as $attrPk => $attrId) {
            $output[$mva[$attrPk]] = array_filter($attrId, function ($attrId) {
                return !!$attrId;
            });
        }

        if ($keysAsKeys) {
            $output = Arrays::mapByKeyMaker($output, function ($attrs, $attrEntity) {
                $attrs || true;
                /** @var Entity $attrEntity */
                return $attrEntity::getPk();
            });
        }

        return $output;
    }

    /**
     * @param ItemEntity $item
     * @param array      $attrs
     *
     * @return Entity|Entity[]|array - [attrEntity => attrObject[]]
     */
    public function getAttrsObjects(ItemEntity $item, array $attrs = [])
    {
        $output = [];

        /** @var Entity|string $attrEntity */

        $sva = PageCatalogManager::getSvaComponents();

        if ($attrs) {
            $sva = array_filter($sva, function ($attrEntity) use ($attrs) {
                /** @var Entity|string $attrEntity */
                return in_array($attrEntity::getPk(), $attrs);
            });
        }

        foreach ($sva as $attrEntity) {
            if ($attrId = $item->get($attrEntity::getPk())) {
                $output[$attrEntity] = $this->app->managers->getByEntityClass($attrEntity)->find($attrId);
            } else {
                $output[$attrEntity] = null;
            }
        }

        foreach ($this->getMva($item, $attrs) as $attrEntity => $attrId) {
            if ($attrId) {
                $output[$attrEntity] = $this->app->managers->getByEntityClass($attrEntity)->findMany($attrId);
            } else {
                $output[$attrEntity] = [];
            }
        }

        return $output;
    }

    /**
     * @param ItemEntity $item
     *
     * @return array
     */
    public function getTypes(ItemEntity $item)
    {
        $output = [];

        $typesToColumns = SRC::getTypesToColumns();

        foreach (URI::TYPE_PARAMS as $type) {
            if (URI::SALES == $type) {
                if (0 < $item->getOldPrice()) {
                    $output[] = $type;
                }
            } else {
                if (1 == $item->get($typesToColumns[$type])) {
                    $output[] = $type;
                }
            }
        }

        return $output;
    }

    /**
     * @param ItemEntity $item
     * @param array      $attrs
     *
     * @return array
     */
    public function getAttrs(ItemEntity $item, array $attrs = [])
    {
        $output = [];

        foreach ($this->getAttrsObjects($item, $attrs) as $attrEntity => $attrObjects) {
            /** @var Entity|string $attrEntity */
            /** @var null|Entity|Entity[] $attrObjects */
            if ($attrObjects) {
                $output[$attrEntity::getPk()] = $attrObjects;
            }
        }

        return $output;
    }

    public function getPriceRanges()
    {
        $output = [];

        $tmp = explode(',', $this->app->config->catalog->price);
        array_unshift($tmp, 0);
        $tmp[] = 9999999;

        for ($i = 0, $s = count($tmp) - 1; $i < $s; $i++) {
            $output[$i] = [$tmp[$i], $tmp[$i + 1]];
        }

        return $output;
    }


    /**
     * @param URI $uri
     *
     * @return string
     */
    public function getPricesByUriCacheKey(URI $uri)
    {
        return implode('-', [
            $this->entity->getTable(),
            md5(serialize([
                $this->getParams(),
                $uri->getParamsByTypes('filter'),
//                $this->getPriceRanges()
            ])),
            'price-range'
        ]);
    }

    /**
     * @todo replace inner selects with joins in case of Rdbms...
     *
     * @param URI $uri
     *
     * @return array|bool|mixed
     */
    public function getPricesByUri(URI $uri)
    {
        $cache = $this->getCacheObject();
        $k = $this->getPricesByUriCacheKey($uri);

        if (false !== ($output = $cache->get($k))) {
            return $output;
        }

        $items = [];

        foreach ($this->getDataProvider()->getPricesByUri($uri) as $row) {
            foreach ($row as $k => $v) {
                if (1 == $v && 'cnt' != $k) {
                    $tmp2 = explode('_', $k);
                    $items[] = (object)[
                        'from' => (int)$tmp2[1],
                        'to' => (int)$tmp2[2],
                        'cnt' => $row['cnt']
                    ];
                }
            }
        }

        usort($items, function ($a, $b) {
            if ($a->from == $b->from) {
                return 0;
            }

            return ($a->from > $b->from) ? 1 : -1;
        });

        $cache->set($k, $items);
        return $items;
    }

    public function getTypesByUriCacheKey(URI $uri)
    {
        return implode('-', [
            $this->entity->getTable(),
            md5(serialize([
                $this->getParams(),
                $uri->getParamsByTypes('filter')
            ])),
            'ids'
        ]);
    }

    /**
     * Keep category and types (in links)
     *
     * @param URI $uri
     *
     * @return array|bool|mixed
     */
    public function getTypesByUri(URI $uri)
    {
        $cache = $this->getCacheObject();
        $k = $this->getTypesByUriCacheKey($uri);

        if (false !== ($output = $cache->get($k))) {
            return $output;
        }

        $tmp = $this->getDataProvider()->getTypesByUri($uri, $map, $current);

        $tmp2 = [];

        foreach ($map as $uriKey => $dbKey) {
            if ($current[$uriKey]) {
                $tmp2[$uriKey] = null;
            } else {
                $tmp2[$uriKey] = 0;

                $state = array_merge($current, [$uriKey => 1]);

                foreach ($tmp as $row) {
                    foreach ($state as $uriKey2 => $isset) {
                        if ($isset && !$row[$map[$uriKey2]]) {
                            continue 2;
                        }
                    }

                    $tmp2[$uriKey] += $row['cnt'];
                }
            }
        }

        $output = [];

        foreach ($tmp2 as $type => $count) {
            if ((null === $count) || ($count > 0)) {
                $output[$type] = (object)[
                    'name' => $type,
                    'count' => $count,
                    'active' => !!$uri->get($type)
                ];
            }
        }

        $cache->set($k, $output);
        return $output;
    }

    /**
     * @param array|null $where
     * @param int        $countPerGroup
     *
     * @return array
     */
    public function getFirstItemsFromEachCategory(array $where = null, $countPerGroup = 5)
    {
        $this->clear();

        $table = $this->entity->getTable();
        $columns = array_keys($this->entity->getColumns());
        $groupColumn = 'category_id';

        $db = $this->app->services->rdbms(null, null, $this->masterServices);

        $query = new Query(['params' => []]);
        $query->text = implode(' ', [
            $db->makeSelectSQL($columns, false, $query->params),
            'FROM (',
            'SELECT ' . implode(', ', array_merge($columns, ['@n := IF(@g = ' . $db->quote($groupColumn) . ', @n + 1, 1) AS ' . $db->quote('n'), '@g := ' . $db->quote($groupColumn)])),
            'FROM ' . implode(', ', [$db->quote($table), '(SELECT @n := 0, @g := 0) ' . $db->quote('t')]),
            $db->makeWhereSQL($where, $query->params),
            $db->makeOrderSQL([$groupColumn => SORT_ASC, 'created_at' => SORT_DESC, 'partner_updated_at' => SORT_DESC], $query->params),
            ') ' . $db->quote('t2'),
            $db->makeWhereSQL(new Expr($db->quote('n') . ' < ?', $countPerGroup + 1), $query->params)
        ]);

        $output = [];

        foreach ($db->req($query)->reqToArrays() as $item) {
            $id = (int)$item[$groupColumn];

            if (!isset($output[$id])) {
                $output[$id] = [];
            }

            $output[$id][] = $this->populateRow($item);
        }

        return $output;
    }

    /**
     * @param array|null $where
     *
     * @return array
     */
    public function getImageToId(array $where = null)
    {
        $output = [];

        $pk = $this->entity->getPk();

        foreach ($this->copy(true)
//                     ->setStorage($this->storage)
                     ->setColumns([$pk, 'image'])
                     ->setWhere($where)
                     ->setQueryParam('log', false)
                     ->getArrays() as $item) {
            $output[$item['image']] = $item[$pk];
        }

        return $output;
    }

    /**
     * @param ItemEntity $item
     *
     * @return Entity|CategoryEntity
     */
    public function getCategory(ItemEntity $item)
    {
        return $this->getLinked($item, 'category_id');
    }

    /**
     * @param ItemEntity $item
     *
     * @return Entity|BrandEntity
     */
    public function getBrand(ItemEntity $item)
    {
        return $this->getLinked($item, 'brand_id');
    }

    /**
     * @param ItemEntity $item
     *
     * @return Entity|CountryEntity
     */
    public function getCountry(ItemEntity $item)
    {
        return $this->getLinked($item, 'country_id');
    }

    /**
     * @param ItemEntity $item
     *
     * @return Entity|VendorEntity
     */
    public function getVendor(ItemEntity $item)
    {
        return $this->getLinked($item, 'vendor_id');
    }

    public function getGoLink(Entity $entity, $source = null)
    {
        return $this->app->router->makeLink('default', [
            'action' => 'go',
            'type' => 'item',
            'id' => $entity->getId(),
            'source' => $source
        ]);
    }

    public function getLink(Entity $entity, array $params = [], $domain = false)
    {
        /** @var ItemEntity|ItemArchiveEntity $entity */
        $params['uri'] = ItemURI::buildPath($entity->get('name'), $entity->getId());

        return $this->app->router->makeLink('item', $params, $domain);
    }

    public function getEntityCustom(ItemEntity $item)
    {
        if ($v = $item->getEntity()) {
            return $v;
        }

        if ($v = $this->getCategory($item)->getBreadcrumb()) {
            return $v;
        }

        return $this->getCategory($item)->getName();
    }

    public function canCheckRealIsInStock(ItemEntity $item, $strict = false)
    {
        $vendor = $this->getVendor($item);
        return $this->app->managers->vendors->canCheckRealIsInStock($vendor, $strict);
    }

    public function checkRealIsInStock(ItemEntity $item, $strict = false)
    {
        if ($this->canCheckRealIsInStock($item, $strict)) {
            $vendor = $this->getVendor($item);

            if ($adapter = $this->app->managers->vendors->getAdapterObject($vendor)) {
                return $adapter->checkRealIsInStock($item);
            }

            //@todo log error...
        }

        return null;
    }

    /**
     * @param ItemEntity $item
     *
     * @return Entity|Source
     */
    public function getSource(ItemEntity $item)
    {
        return $this->getLinked($item, 'import_source_id');
    }

    public function getImportSource(ItemEntity $item)
    {
        return $this->getSource($item);
    }

    public function getTargetLink(ItemEntity $item)
    {
        $source = $this->getSource($item);
        $import = $this->app->managers->sources->getImport($source);
        return $import->getItemTargetLink($item);
    }
}