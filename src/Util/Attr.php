<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Helper\WalkChunk;
use SNOWGIRL_CORE\Helper\WalkChunk2;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_CORE\Service\Nosql\Mongo;
use SNOWGIRL_CORE\Service\Rdbms\Mysql;
use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_CORE\App;
use SNOWGIRL_SHOP\Item\FixWhere;
use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalogManager;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;

/**
 * Class Attr
 *
 * @property App app
 * @package SNOWGIRL_SHOP\Util
 */
class Attr extends Util
{
    public function doDeleteNonExistingItemsMva()
    {
        $affGlobal = 0;

        $db = $this->app->services->rdbms;

        $itemTable = $this->app->managers->items->getEntity()->getTable();
        $itemPk = $this->app->managers->items->getEntity()->getPk();

        foreach (PageCatalogManager::getMvaComponents() as $class) {
            /** @var \SNOWGIRL_SHOP\Manager\Item\Attr $manager */
            $manager = $this->app->managers->getByEntityClass($class);
            $table = $manager->getEntity()->getTable();
            $pk = $manager->getEntity()->getPk();

            $linkManager = $manager->getMvaLinkManager();
            $linkTable = $linkManager->getEntity()->getTable();

            $aff1 = $db->req(implode(' ', [
                'DELETE ' . $db->quote('ia'),
                'FROM ' . $db->quote($linkTable) . ' ' . $db->quote('ia'),
                'LEFT JOIN ' . $db->quote($table) . ' a USING (' . $db->quote($pk) . ')',
                'WHERE ' . $db->quote($pk, 'a') . ' IS NULL'
            ]))->affectedRows();

            $aff2 = $db->req(implode(' ', [
                'DELETE ' . $db->quote('ia'),
                'FROM ' . $db->quote($linkTable) . ' ' . $db->quote('ia'),
                'LEFT JOIN ' . $db->quote($itemTable) . ' i USING (' . $db->quote($itemPk) . ')',
                'WHERE ' . $db->quote($itemPk, 'i') . ' IS NULL'
            ]))->affectedRows();

            $aff = $aff1 + $aff2;

            $this->output($aff . '[' . $aff1 . '+' . $aff2 . '] ' . $table . ' attrs deleted');

            $affGlobal += $aff;
        }

        return $affGlobal;
    }

    public function getIdToName($entity, $isLowercase = false)
    {
        $output = [];

        $manager = $this->app->managers->getByEntityClass($entity)->copy(true);

        if ($manager->getEntity() instanceof Category) {
            $manager->setOrders([new Expr('LENGTH(`name`) DESC'), 'name' => SORT_ASC]);
        }

        foreach ($manager->getObjects() as $object) {
            $output[$object->getId()] = $object->get('name');
        }

        if ($isLowercase) {
            $output = array_map(function ($i) {
                return mb_strtolower($i);
            }, $output);
        }

        return $output;
    }

    public function getNameToId($entity, $isLowercase = false)
    {
        return array_flip($this->getIdToName($entity, $isLowercase));
    }

    public function getUriToId($entity)
    {
        $manager = $this->app->managers->getByEntityClass($entity);
        $entity = $manager->getEntity();

        $output = [];

        $pk = $entity->getPk();

        foreach ($manager->copy(true)->setColumns([$pk, 'uri'])->getArrays() as $item) {
            $output[$item['uri']] = $item[$pk];
        }

        return $output;
    }

    public function doAddMvaByInclusions(FixWhere $fixWhere = null, array $tables = [])
    {
        $allowedTables = array_map(function ($component) {
            /** @var Entity $component */
            return $component::getTable();
        }, PageCatalogManager::getMvaComponents());

        //@todo add for Sizes too...
        $allowedTables = array_diff($allowedTables, [$this->app->managers->sizes->getEntity()->getTable()]);

        $tables = array_filter($tables, function ($table) use ($allowedTables) {
            return strlen($table) && in_array($table, $allowedTables);
        });

        $tables = $tables ?: $allowedTables;

        $itemPk = $this->app->managers->items->getEntity()->getPk();

        $defaultColumns = ['name', 'entity', 'description'];

        $columns = [
            $this->app->managers->tags->getEntity()->getTable() => ['name', 'entity'],
            $this->app->managers->colors->getEntity()->getTable() => $defaultColumns,
            $this->app->managers->materials->getEntity()->getTable() => $defaultColumns,
            $this->app->managers->sizes->getEntity()->getTable() => ['description'],
            $this->app->managers->seasons->getEntity()->getTable() => ['description']
        ];

        /** @var Manager[] $managers */
        $managers = [];
        $pks = [];
        $attrs = [];
        $terms = [];

        foreach ($tables as $table) {
            /** @var ItemAttrManager $manager */
            $manager = $this->app->managers->getByTable($table);
            $entity = $manager->getEntity();
            $pks[$table] = $entity->getPk();
            $attrs[$table] = $this->getNameToId($entity->getClass(), true);
            $terms[$table] = $manager->getTermsManager()->getValueToComponentId(['lang' => 'ru']);
            $managers[$table] = $manager->getMvaLinkManager();

            if (!isset($columns[$table])) {
                $columns[$table] = $defaultColumns;
            }
        }

        $leafCategoriesIds = $this->app->managers->categories->getLeafsIds();

        $manager = $this->app->managers->items->clear()
            ->setColumns($itemPk)
            ->addColumn(array_unique(call_user_func_array('array_merge', $columns)))
            ->addColumn('category_id');

        if ($fixWhere && $where = $fixWhere->get()) {
            $manager->setWhere($where);
        }

        (new WalkChunk(1000))
            ->setFnGet(function ($page, $size) use ($manager) {
                return $manager->setOffset(($page - 1) * $size)
                    ->setLimit($size)
                    ->getArrays();
            })
            ->setFnDo(function ($items) use ($itemPk, $columns, $tables, $managers, $pks, $attrs, $terms, $leafCategoriesIds) {
                $inserts = [];

                foreach ($tables as $table) {
                    $inserts[$table] = [];
                }

                foreach ($items as $item) {
                    foreach ($tables as $table) {
                        if ('tag' == $table && !in_array($item['category_id'], $leafCategoriesIds)) {
                            continue;
                        }

                        $output = [];

                        foreach ($attrs[$table] as $attr => $id) {
                            foreach ($columns[$table] as $column) {
                                if (false !== mb_stripos($item[$column], $attr)) {
                                    $output[] = $id;
                                    break;
                                }
                            }
                        }

                        foreach ($terms[$table] as $term => $id) {
                            foreach ($columns[$table] as $column) {
                                if (false !== mb_stripos($item[$column], $term)) {
                                    $output[] = $id;
                                    break;
                                }
                            }
                        }

                        foreach (array_unique($output) as $id) {
                            $inserts[$table][] = [
                                $itemPk => $item[$itemPk],
                                $pks[$table] => $id
                            ];
                        }
                    }
                }

                foreach ($inserts as $table => $insert) {
                    if ($insert && $aff = $managers[$table]->insertMany($insert, true)) {
                        $this->app->services->logger->make($aff . ' updated for attr="' . $table . '"');
                    }
                }
            })
            ->run();

        return true;
    }

    public function doInMongoTransfer(array $attrs = [])
    {
        /** @var Mysql $rdbms */
        $rdbms = $this->app->services->rdbms;

        /** @var Mongo $nosql */
        $nosql = $this->app->services->nosql;

        $affGlobal = 0;

        foreach (array_merge(PageCatalogManager::getMvaComponents(), PageCatalogManager::getSvaComponents()) as $class) {
            //        $nosql->dropSchema($nosql->getSchema());

            $manager = $this->app->managers->getByEntityClass($class);

            $entity = $manager->getEntity();

            $table = $entity->getTable();

            if ($attrs && !in_array($table, $attrs)) {
                continue;
            }

            $pk = $entity->getPk();

//            $nosql->dropCollection($table);

            if (!$nosql->createCollection($table)) {
                return false;
            }

            $columns = array_keys($entity->getColumns());

            $aff = 0;

            (new WalkChunk2(1000))
                ->setFnGet(function ($lastId, $size) use ($rdbms, $pk, $table, $columns) {
                    $query = new Query(['params' => []]);
                    $query->text = implode(' ', [
                        'SELECT ' . implode(', ', array_map(function ($column) use ($rdbms, $table) {
                            return $rdbms->quote($column, $table);
                        }, $columns)),
                        'FROM ' . $rdbms->quote($table),
                        $lastId ? $rdbms->makeWhereSQL(new Expr($rdbms->quote($pk, $table) . ' > ?', $lastId), $query->params, $table) : '',
                        $rdbms->makeGroupSQL($pk, $query->params, $table),
                        $rdbms->makeOrderSQL([$pk => SORT_ASC], $query->params, $table),
                        $rdbms->makeLimitSQL(0, $size, $query->params)
                    ]);

                    return $rdbms->req($query)->reqToArrays();
                })
                ->setFnDo(function ($items) use ($nosql, $entity, $pk, $table, &$aff) {
                    $items = array_map(function ($item) use ($pk, $entity) {
                        $item = array_filter($item, function ($v) {
                            return null !== $v;
                        });

                        $item['_id'] = $item[$pk];

                        foreach ($entity->getColumns() as $column => $options) {
                            if (isset($item[$column])) {
                                switch ($options['type']) {
                                    case Entity::COLUMN_FLOAT:
                                        $item[$column] = (float)$item[$column];
                                        break;
                                    case Entity::COLUMN_INT:
                                        $item[$column] = (int)$item[$column];
                                        break;
                                    case Entity::COLUMN_TIME:
                                        if ($item[$column]) {
//                                    $item[$column] = new UTCDateTime(strtotime($item[$column]) * 1000);
                                        } else {
                                            $item[$column] = $options['default'];
                                        }
                                        break;
                                    default:
                                        break;
                                }
                            }
                        }

                        return $item;
                    }, $items);

                    $aff += $nosql->insertMany($table, $items);

                    return ($last = array_pop($items)) ? $last[$pk] : false;
                })
                ->run();

            $this->output($table . ' aff=' . $aff);
            $affGlobal += $aff;
        }

        return $affGlobal;
    }
}