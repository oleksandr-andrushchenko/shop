<?php

namespace SNOWGIRL_SHOP\Catalog\SRC\DataProvider;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_SHOP\Catalog\SRC\DataProvider;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Item;

use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalogManager;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;

class Mysql extends DataProvider
{
    public function getItemsAttrs()
    {
        $table = Item::getTable();
        $pk = Item::getPk();

        $db = $this->src->getURI()->getApp()->storage->mysql(null, $this->src->getMasterServices());

        $offset = $this->src->getOffset();
        $limit = $this->src->getLimit();

        $columns = [new Expr($db->quote($pk, $table))];
        $joins = [];
        $where = $this->getWhere(true);
//        $whereIndex = $this->getWhereIndex($where);
        $order = $this->getOrder(!$this->src->getMaxMatched());

        $mva = Manager::mapEntitiesAddPksAsKeys(PageCatalogManager::getMvaComponents());

        $attrWhats = [];
        $attrJoins = [];
        $attrOrders = [];

        $attrsKeysAlreadyAdded = [];

        $makeJoin = function ($entity, $strict = true) use ($pk, $table, $db) {
            /** @var Entity $entity */
            $table2 = 'item_' . $entity::getTable();
            return ($strict ? 'INNER' : 'LEFT') . '  JOIN ' . $db->quote($table2) . ' ON ' . $db->quote($pk, $table) . ' = ' . $db->quote('item_id', $table2);
        };

        $makeWhat = function ($entity, $multi = true) use ($db) {
            /** @var Entity $entity */
            $key = $entity::getPk();

            if ($multi) {
                return new Expr('GROUP_CONCAT(DISTINCT ' . $db->quote($key) . ') AS ' . $db->quote($key));
            }

            return $key;
        };

        foreach ($where as $key => $value) {
            if (isset($mva[$key])) {
                $entity = $mva[$key];

                $attrJoins[] = $makeJoin($entity, true);

                if (isset($this->entities[$key])) {
                    $attrWhats[] = $makeWhat($entity, true);
                }

                if ($this->src->getMaxMatched()) {
                    $attrOrders[] = 'COUNT(DISTINCT ' . $db->quote($key) . ')';
                }

                $attrsKeysAlreadyAdded[] = $key;
            }
        }

        foreach ($this->src->getEntities() as $key => $entity) {
            if (!in_array($key, $attrsKeysAlreadyAdded)) {
                if (isset($mva[$key])) {
                    $attrJoins[] = $makeJoin($entity, false);
                    $attrWhats[] = $makeWhat($entity, true);
                } else {
                    $attrWhats[] = $makeWhat($entity, false);
                }
            }
        }

        if ($attrWhats) {
            $columns = array_merge($columns, $attrWhats);
        }

        if ($attrJoins) {
            $joins = array_merge($joins, $attrJoins);
        }

        if ($attrOrders) {
//            $orderIndex = false;

            /**
             * @see ::makeOrder
             */
            $addNewOrderAfter = 'is_in_stock';
            $key = false === ($tmp = array_search($addNewOrderAfter, array_keys($order))) ? -1 : $tmp;

            $order = array_slice($order, 0, ++$key, true) +
                [new Expr(implode(' + ', $attrOrders) . ' DESC')] +
                array_slice($order, $key, null, true);
        }

//        $index = [];

//        if ($whereIndex) {
//            $index = array_merge($index, is_array($whereIndex) ? $whereIndex : [$whereIndex]);
//        }

//        if ($orderIndex) {
//            $index = array_merge($index, is_array($orderIndex) ? $orderIndex : [$orderIndex]);
//        }

        //@todo indexes...

        //we could fetch multi value attributes also - GROUP_CONCAT(DISTINCT t.tag_id), GROUP_CONCAT(DISTINCT m.material_id)

        $query = new Query(['params' => []]);
        $query->text = implode(' ', [
            $db->makeSelectSQL($columns, false, $query->params),
            $db->makeFromSQL($table),
//            $index ? $db->makeIndexSQL($index) : '',
            implode(' ', $joins),
            $db->makeWhereSQL($where, $query->params),
            $joins ? $db->makeGroupSQL($pk, $query->params, $table) : '',
            $db->makeOrderSQL($order, $query->params),
            $db->makeLimitSQL($offset, $limit, $query->params),
        ]);

        $output = $db->req($query)->reqToArrays();

//        var_dump($output);die;
        return $output;
    }

    public function getWhere($raw = false)
    {
        $output = [];

        $params = $this->src->getURI()->getParamsByTypes('filter');

        if (isset($params[URI::SPORT])) {
            $output['is_sport'] = 1;
        } else {
            if (!isset($params['tag_id'])) {
                $output['is_sport'] = 0;
            }
        }

        if (isset($params[URI::SIZE_PLUS])) {
            $output['is_size_plus'] = 1;
        } else {
            if (!isset($params['tag_id'])) {
                $output['is_size_plus'] = 0;
            }
        }

        $db = $this->src->getURI()->getApp()->storage->mysql(null, $this->src->getMasterServices());

        $mva = Manager::mapEntitiesAddPksAsKeys(PageCatalogManager::getMvaComponents());

        foreach (Manager::mapEntitiesAddPksAsKeys(PageCatalogManager::getComponentsOrderByRdbmsKey()) as $pk => $entity) {
            if (isset($params[$pk])) {
                $v = $params[$pk];

                if (is_array($v)) {
                    array_walk($v, function (&$vv) {
                        $vv = (int)$vv;
                    });
                } else {
                    $v = (int)$v;
                }

                /**
                 * @todo ...
                 * @todo describe & implement categories logic...
                 * Multi-Value Attributes goes with OR operator
                 * Single-Value Attributes goes with OR operator
                 *
                 * General Where goes with AND operator
                 */
                if (isset($mva[$pk]) && !$raw) {
                    $bind = [];

                    $output[$pk] = new Expr($db->quote('item_id') . ' IN (' . implode(' ', [
                            $db->makeSelectSQL('item_id', false, $bind),
                            $db->makeFromSQL(ItemAttrManager::makeLinkTableNameByEntityClass($mva[$pk])),
                            $db->makeWhereSQL([$pk => $v], $bind)
                        ]) . ')', ...$bind);
                } else {
                    $output[$pk] = $v;
                }
            }
        }

        if (isset($output['category_id'])) {
            $categoryId = (int)$params['category_id'];
            $output['category_id'] = $this->src->getURI()->getApp()->managers->categories->getChildrenIdFor($categoryId);
        }

        if (isset($params[URI::PRICE_FROM])) {
            $output[URI::PRICE_FROM] = new Expr($db->quote('price') . ' > ?', (int)$params[URI::PRICE_FROM]);
        }

        if (isset($params[URI::PRICE_TO])) {
            $output[URI::PRICE_TO] = new Expr($db->quote('price') . ' <= ?', (int)$params[URI::PRICE_TO]);
        }

        if (isset($params[URI::SALES])) {
            $output[URI::SALES] = new Expr($db->quote('old_price') . ' > 0');
        }

        return $output;
    }

    /**
     * Main Order function - returns Rdbms(!) order
     *
     * @todo if change - sync with tables order columns...
     *
     * @param bool|false $cache
     *
     * @return array
     */
    public function getOrder($cache = false)
    {
        $output = [];

        $info = $this->src->getOrderInfo();

        if ($cache) {
            //this column should be enumerated according to this method non-cache output
            $output[$info->cache_column] = SORT_ASC;
//            $index = $info->cache_index;
            return $output;
        }

        $output['is_in_stock'] = SORT_DESC;

        $output[$info->column] = $info->desc ? SORT_DESC : SORT_ASC;

        $output['created_at'] = SORT_DESC;
        $output['partner_updated_at'] = SORT_DESC;

        if (!isset($output['rating'])) {
            $output['rating'] = SORT_DESC;
        }

        $output['item_id'] = SORT_DESC;

        return $output;
    }

    public function getTotalCount()
    {
        return $this->src->getURI()->getApp()->managers->items->clear()
            ->setStorageObject($this->src->getURI()->getApp()->storage->mysql(null, $this->src->getMasterServices()))
            ->setWhere($this->getWhere())
            ->getCount();
    }
}