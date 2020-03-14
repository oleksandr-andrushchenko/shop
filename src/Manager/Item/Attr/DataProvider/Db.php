<?php

namespace SNOWGIRL_SHOP\Manager\Item\Attr\DataProvider;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Item\Attr\DataProvider;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalogManager;

class Db extends DataProvider
{
    use \SNOWGIRL_CORE\Manager\DataProvider\Traits\Db;

    public function getFiltersCountsByUri(URI $uri, $query = null, $prefix = false)
    {
        $simple = $this->manager->getApp()->config('catalog.use_simple_queries', false);

        $pk = $this->manager->getEntity()->getPk();
        $class = $this->manager->getEntity()->getClass();

        $where = $uri->getSRC()->getDataProvider('db')->getWhere();

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

        $db = $this->manager->getApp()->container->db($this->manager->getMasterServices());

        if (in_array($class, $mva)) {
            $linkTable = $this->manager->makeLinkTableNameByEntityClass($class);

            if (array_diff(array_keys($where), array_keys($mva))) {
                $tableFrom = $itemTable;
                $joins[] = new Expression('INNER JOIN ' . $db->quote($linkTable) . ' USING(' . $db->quote($itemPk) . ')');
            } else {
                $tableFrom = $linkTable;
            }

            $columns[] = new Expression($db->quote($pk, $linkTable));
            $groups[] = new Expression($db->quote($pk, $linkTable));
        } else {
            $tableFrom = $itemTable;
            $columns[] = new Expression($db->quote($pk, $tableFrom));
            $groups[] = new Expression($db->quote($pk, $tableFrom));
        }

        $columns[] = new Expression('COUNT(*) AS ' . $db->quote('cnt'));

        if ($this->manager->getQuery()->limit) {
            if ($simple) {
                $orders[] = new Expression($db->quote('cnt') . ' DESC');
            } else {
                $orders[] = new Expression('ROUND(COUNT(*) / 1000) DESC');
                $orders[] = new Expression('ROUND(' . $db->quote('rating', $table) . ' / 100) DESC');
            }
        }

        if ($this->manager->getQuery()->limit || $query) {
            if ($simple) {
                if ($query) {
                    $joins[] = new Expression('INNER JOIN ' . $db->quote($table) . ' USING(' . $db->quote($pk) . ')');
                }
            } else {
                $joins[] = new Expression('INNER JOIN ' . $db->quote($table) . ' USING(' . $db->quote($pk) . ')');
            }

            if ($query) {
                //@todo if changed - sync with parent::getObjectsByQuery()
                $qc = $db->quote($this->manager->findColumns(Entity::SEARCH_IN)[0], $table);
                $columns[] = new Expression('CHAR_LENGTH(' . $qc . ') AS ' . $db->quote('length'));
                $where[] = new Expression($qc . ' LIKE ?', ($prefix ? '' : '%') . $query . '%');
                $orders[] = new Expression('CASE WHEN ' . $qc . ' LIKE ? THEN 1 ELSE 2 END', $query . '%');
                $orders[] = new Expression($db->quote('length') . ' ASC');
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

        return $db->selectMany($tableFrom, $this->manager->getQuery()->merge([
            'columns' => $columns,
            'joins' => $joins,
            'where' => $where,
            'groups' => $groups,
            'orders' => $orders,
            'offset' => $this->manager->getQuery()->offset,
            'limit' => $this->manager->getQuery()->limit,
            'indexes' => $index
        ]));
    }
}