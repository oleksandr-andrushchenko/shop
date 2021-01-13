<?php

namespace SNOWGIRL_SHOP\Manager\Item\Attr\DataProvider;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Mysql\MysqlQueryExpression;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Item\Attr\DataProvider;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalogManager;

class MysqlDataProvider extends DataProvider
{
    use \SNOWGIRL_CORE\Manager\DataProvider\Traits\MysqlDataProvider;

    public function getFiltersCountsByUri(URI $uri, string $query = null, bool $prefix = false): array
    {
        $simple = $this->manager->getApp()->config('catalog.simple_queries', false);

        $pk = $this->manager->getEntity()->getPk();
        $class = $this->manager->getEntity()->getClass();

        $where = $uri->getSRC()->getDataProvider('mysql')->getWhere();

        if ($this->manager->getQuery()->where) {
            $where = array_merge($where, Arrays::cast($this->manager->getQuery()->where));
        }

        $mva = $this->manager->mapEntitiesAddPksAsKeys(PageCatalogManager::getMvaComponents());

        unset($where[$pk]);

        $table = $this->manager->getEntity()->getTable();

        $columns = [];
        $joins = [];

        $groups = [];
        $orders = [];

        $itemTable = $this->manager->getApp()->managers->items->getEntity()->getTable();
        $itemPk = $this->manager->getApp()->managers->items->getEntity()->getPk();

        $mysql = $this->manager->getApp()->container->mysql($this->manager->getMasterServices());

        if (in_array($class, $mva)) {
            $linkTable = $this->manager->makeLinkTableNameByEntityClass($class);

            if (array_diff(array_keys($where), array_keys($mva))) {
                $tableFrom = $itemTable;
                $joins[] = new MysqlQueryExpression('INNER JOIN ' . $mysql->quote($linkTable) . ' USING(' . $mysql->quote($itemPk) . ')');
            } else {
                $tableFrom = $linkTable;
            }

            $columns[] = new MysqlQueryExpression($mysql->quote($pk, $linkTable));
            $groups[] = new MysqlQueryExpression($mysql->quote($pk, $linkTable));
        } else {
            $tableFrom = $itemTable;
            $columns[] = new MysqlQueryExpression($mysql->quote($pk, $tableFrom));
            $groups[] = new MysqlQueryExpression($mysql->quote($pk, $tableFrom));
        }

        $columns[] = new MysqlQueryExpression('COUNT(*) AS ' . $mysql->quote('cnt'));

        if ($this->manager->getQuery()->limit) {
            if ($simple) {
                $orders[] = new MysqlQueryExpression($mysql->quote('cnt') . ' DESC');
            } else {
                $orders[] = new MysqlQueryExpression('ROUND(COUNT(*) / 1000) DESC');
                $orders[] = new MysqlQueryExpression('ROUND(' . $mysql->quote('rating', $table) . ' / 100) DESC');
            }
        }

        if ($this->manager->getQuery()->limit || $query) {
            if ($simple) {
                if ($query) {
                    $joins[] = new MysqlQueryExpression('INNER JOIN ' . $mysql->quote($table) . ' USING(' . $mysql->quote($pk) . ')');
                }
            } else {
                $joins[] = new MysqlQueryExpression('INNER JOIN ' . $mysql->quote($table) . ' USING(' . $mysql->quote($pk) . ')');
            }

            if ($query) {
                //@todo if changed - sync with parent::getObjectsByQuery()
                $qc = $mysql->quote($this->manager->findColumns(Entity::SEARCH_IN)[0], $table);
                $columns[] = new MysqlQueryExpression('CHAR_LENGTH(' . $qc . ') AS ' . $mysql->quote('length'));
                $where[] = new MysqlQueryExpression($qc . ' LIKE ?', ($prefix ? '' : '%') . $query . '%');
                $orders[] = new MysqlQueryExpression('CASE WHEN ' . $qc . ' LIKE ? THEN 1 ELSE 2 END', $query . '%');
                $orders[] = new MysqlQueryExpression($mysql->quote('length') . ' ASC');
            }
        }

        if ($simple) {
            $index = null;
        } else {
            if ($itemTable == $tableFrom) {
                if ($index = $this->manager->getApp()->managers->items->findIndexes($where)) {
                    $index = (1 < count($index)) ? null : $index[0];
                } else {
                    $index = null;
                }
            } else {
                $index = null;
            }
        }

        return $mysql->selectMany($tableFrom, $this->manager->getQuery()->merge([
            'columns' => $columns,
            'joins' => $joins,
            'where' => $where,
            'groups' => $groups,
            'orders' => $orders,
            'offset' => $this->manager->getQuery()->offset,
            'limit' => $this->manager->getQuery()->limit,
            'indexes' => $index,
        ]));
    }
}