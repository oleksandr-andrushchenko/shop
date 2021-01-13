<?php

namespace SNOWGIRL_SHOP\Catalog\SRC\DataProvider;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Mysql\MysqlQuery;
use SNOWGIRL_CORE\Mysql\MysqlQueryExpression;
use SNOWGIRL_SHOP\Catalog\SRC\DataProvider;
use SNOWGIRL_SHOP\Catalog\URI;

use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;

class MysqlDataProvider extends DataProvider
{
    public function getItemsAttrs(): array
    {
        $table = $this->src->getURI()->getApp()->managers->items->getEntity()->getTable();
        $pk = $this->src->getURI()->getApp()->managers->items->getEntity()->getPk();

        $mysql = $this->src->getURI()->getApp()->container->mysql($this->src->getMasterServices());

        $offset = $this->src->getOffset();
        $limit = $this->src->getLimit();

        $columns = [new MysqlQueryExpression($mysql->quote($pk, $table))];
        $joins = [];
        $where = $this->getWhere(true);
        $order = $this->getOrder();

        $mva = Manager::mapEntitiesAddPksAsKeys($this->src->getURI()->getApp()->managers->catalog->getMvaComponents());

        $attrWhats = [];
        $attrJoins = [];
        $attrOrders = [];

        $attrsKeysAlreadyAdded = [];

        $makeJoin = function ($entity, $strict = true) use ($pk, $table, $mysql) {
            /** @var Entity $entity */
            $table2 = 'item_' . $entity::getTable();
            return ($strict ? 'INNER' : 'LEFT') . '  JOIN ' . $mysql->quote($table2) . ' ON ' . $mysql->quote($pk, $table) . ' = ' . $mysql->quote('item_id', $table2);
        };

        $makeWhat = function ($entity, $multi = true) use ($mysql) {
            /** @var Entity $entity */
            $key = $entity::getPk();

            if ($multi) {
                return new MysqlQueryExpression('GROUP_CONCAT(DISTINCT ' . $mysql->quote($key) . ') AS ' . $mysql->quote($key));
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
            /**
             * @see ::makeOrder
             */
            $addNewOrderAfter = 'is_in_stock';
            $key = false === ($tmp = array_search($addNewOrderAfter, array_keys($order))) ? -1 : $tmp;

            $order = array_slice($order, 0, ++$key, true) +
                [new MysqlQueryExpression(implode(' + ', $attrOrders) . ' DESC')] +
                array_slice($order, $key, null, true);
        }

        //we could fetch multi value attributes also - GROUP_CONCAT(DISTINCT t.tag_id), GROUP_CONCAT(DISTINCT m.material_id)

        $query = new MysqlQuery(['params' => []]);
        $query->text = implode(' ', [
            $mysql->makeSelectSQL($columns, false, $query->params),
            $mysql->makeFromSQL($table),
            implode(' ', $joins),
            $mysql->makeWhereSQL($where, $query->params, null, $query->placeholders),
            $joins ? $mysql->makeGroupSQL($pk, $query->params, $table) : '',
            $mysql->makeOrderSQL($order, $query->params),
            $mysql->makeLimitSQL($offset, $limit, $query->params),
        ]);

        return $mysql->reqToArrays($query);
    }

    public function getWhere(bool $raw = false): array
    {
        $output = [
            'is_in_stock' => 1,
        ];

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

        $mysql = $this->src->getURI()->getApp()->container->mysql($this->src->getMasterServices());

        $mva = Manager::mapEntitiesAddPksAsKeys($this->src->getURI()->getApp()->managers->catalog->getMvaComponents());

        foreach (Manager::mapEntitiesAddPksAsKeys($this->src->getURI()->getApp()->managers->catalog->getComponentsOrderByDbKey()) as $pk => $entity) {
            if (isset($params[$pk])) {
                $v = $params[$pk];

                if (is_array($v)) {
                    array_walk($v, function (&$vv) {
                        $vv = (int) $vv;
                    });
                } else {
                    $v = (int) $v;
                }

                /**
                 * @todo ...
                 * @todo describe & implement categories logic...
                 * Multi-Value Attributes goes with OR operator
                 * Single-Value Attributes goes with OR operator
                 * General Where goes with AND operator
                 */
                if (isset($mva[$pk]) && !$raw) {
                    $bind = [];

                    $output[$pk] = new MysqlQueryExpression($mysql->quote('item_id') . ' IN (' . implode(' ', [
                            $mysql->makeSelectSQL('item_id', false, $bind),
                            $mysql->makeFromSQL(ItemAttrManager::makeLinkTableNameByEntityClass($mva[$pk])),
                            $mysql->makeWhereSQL([$pk => $v], $bind)
                        ]) . ')', ...$bind);
                } else {
                    $output[$pk] = $v;
                }
            }
        }

        if (isset($output['category_id'])) {
            $categoryId = (int) $params['category_id'];
            $output['category_id'] = $this->src->getURI()->getApp()->managers->categories->getChildrenIdFor($categoryId);
        }

        if (isset($params[URI::PRICE_FROM])) {
            $output[URI::PRICE_FROM] = new MysqlQueryExpression($mysql->quote('price') . ' > ?', (int) $params[URI::PRICE_FROM]);
        }

        if (isset($params[URI::PRICE_TO])) {
            $output[URI::PRICE_TO] = new MysqlQueryExpression($mysql->quote('price') . ' <= ?', (int) $params[URI::PRICE_TO]);
        }

        if (isset($params[URI::SALES])) {
            $output[URI::SALES] = new MysqlQueryExpression($mysql->quote('old_price') . ' > 0');
        }

        return $output;
    }

    public function getOrder(bool $cache = false): array
    {
        $output = [];

        $info = $this->src->getOrderInfo();

        if ($cache) {
            $output[$info->cache_column] = SORT_ASC;
            return $output;
        }

//        $output['created_at'] = SORT_DESC;
//        $output['partner_updated_at'] = SORT_DESC;

        if ($info->column) {
            $output[$info->column] = $info->order ? SORT_DESC : SORT_ASC;
        }

//        $output['created_at'] = SORT_DESC;
//        $output['partner_updated_at'] = SORT_DESC;

//        if (!isset($output['rating'])) {
//            $output['rating'] = SORT_DESC;
//        }

//        $output['item_id'] = SORT_DESC;

        return $output;
    }

    public function getTotalCount(): int
    {
        return $this->src->getURI()->getApp()->managers->items->clear()
            ->setMysql($this->src->getURI()->getApp()->container->mysql($this->src->getMasterServices()))
            ->setWhere($this->getWhere())
            ->getCount();
    }
}