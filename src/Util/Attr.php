<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Helper\WalkChunk;
use SNOWGIRL_CORE\Helper\WalkChunk2;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_CORE\Service\Nosql\Mongo;
use SNOWGIRL_CORE\Service\Db\Mysql;
use SNOWGIRL_CORE\Query;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_SHOP\App\Console as App;
use SNOWGIRL_SHOP\Item\FixWhere;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;
use SNOWGIRL_SHOP\Manager\Term as TermManager;

/**
 * Class Attr
 *
 * @property App app
 * @package SNOWGIRL_SHOP\Util
 */
class Attr extends Util
{
    public function doDeleteNonExistingItemsMva(FixWhere $fixWhere = null, array $params = [])
    {
        $aff = 0;

        $db = $this->app->container->db;

        $itemTable = $this->app->managers->items->getEntity()->getTable();
        $itemPk = $this->app->managers->items->getEntity()->getPk();

        foreach ($this->app->managers->catalog->getMvaComponents() as $class) {
            /** @var ItemAttrManager $manager */
            $manager = $this->app->managers->getByEntityClass($class);
            $table = $manager->getEntity()->getTable();
            $pk = $manager->getEntity()->getPk();

            $linkManager = $manager->getMvaLinkManager();
            $linkTable = $linkManager->getEntity()->getTable();

            $query = new Query();
            $query->params = [];
            $query->text = implode(' ', [
                'DELETE ' . $db->quote('ia'),
                'FROM ' . $db->quote($linkTable) . ' ' . $db->quote('ia'),
                'LEFT JOIN ' . $db->quote($table) . ' ' . $db->quote('a') . ' USING (' . $db->quote($pk) . ')',
                'WHERE ' . $db->quote($pk, 'a') . ' IS NULL'
            ]);
            $query->merge($params);

            $affTmp1 = $db->req($query)->affectedRows();

            $this->output($affTmp1 . ' deleted from ' . $linkTable . ' [not exists in ' . $table . ']');

            $where = $fixWhere ? $fixWhere->get() : [];
            $where[] = new Expression($db->quote($itemPk, $itemTable) . ' IS NULL');

            $query = new Query();
            $query->params = [];
            $query->text = implode(' ', [
                'DELETE ' . $db->quote('ia'),
                'FROM ' . $db->quote($linkTable) . ' ' . $db->quote('ia'),
                'LEFT JOIN ' . $db->quote($itemTable) . ' USING (' . $db->quote($itemPk) . ')',
                $db->makeWhereSQL($where, $query->params)
            ]);
            $query->merge($params);

            $affTmp2 = $db->req($query)->affectedRows();

            $this->output($affTmp2 . ' deleted from ' . $linkTable . ' [not exists in ' . $itemTable . ']');

            $affTmp = $affTmp1 + $affTmp2;

            $this->output($affTmp . ' deleted in total from ' . $linkTable);
            $aff += $affTmp;
        }

        return $aff;
    }

    public function getIdToName($entity, $isLowercase = false): array
    {
        $output = [];

        $manager = $this->app->managers->getByEntityClass($entity)->copy(true);
        $pk = $manager->getEntity()->getPk();

        if ($manager->getEntity() instanceof Category) {
            $manager->setOrders([new Expression('LENGTH(`name`) DESC'), 'name' => SORT_ASC]);
        }

        foreach ($manager->setColumns([$pk, 'name'])->getArrays() as $row) {
            $output[$row[$pk]] = $row['name'];
        }

        if ($isLowercase) {
            $output = array_map(function ($i) {
                return mb_strtolower($i);
            }, $output);
        }

        return $output;
    }

    public function getNameToId($entity, $isLowercase = false): array
    {
        return array_flip($this->getIdToName($entity, $isLowercase));
    }

    public function getUriToId($entity): array
    {
        $output = [];

        $manager = $this->app->managers->getByEntityClass($entity);
        $pk = $manager->getEntity()->getPk();

        foreach ($manager->copy(true)->setColumns([$pk, 'uri'])->getArrays() as $row) {
            $output[$row['uri']] = $row[$pk];
        }

        return $output;
    }

    public function getNameToIdAndUriToId(Manager $manager, $isLowercase = false): array
    {
        $output = [[], []];

        $pk = $manager->getEntity()->getPk();

        if ($manager->getEntity() instanceof Category) {
            $manager->setOrders([new Expression('LENGTH(`name`) DESC'), 'name' => SORT_ASC]);
        }

        $manager->setColumns([$pk, 'name', 'uri']);

        if ($isLowercase) {
            foreach ($manager->getArrays() as $row) {
                $output[0][mb_strtolower($row['name'])] = (int)$row[$pk];
                $output[1][$row['uri']] = $row[$pk];
            }
        } else {
            foreach ($manager->getArrays() as $row) {
                $output[0][$row['name']] = (int)$row[$pk];
                $output[1][$row['uri']] = (int)$row[$pk];
            }
        }

        return $output;
    }

    public function getTermNameToAttrId(TermManager $manager, $isLowercase = false)
    {
        $output = [];

        foreach ($manager->getObjects() as $term) {
            /** @var TermEntity $term */
            $output[$term->getValue()] = $term->getComponentId();
        }

        if ($isLowercase) {
            $output = Arrays::mapByKeyMaker($output, function ($key) {
                return mb_strtolower($key);
            });
        }

        return $output;
    }

    public function doAddMvaByInclusions(FixWhere $fixWhere = null, array $tables = [])
    {
        $allowedTables = array_map(function ($component) {
            /** @var Entity $component */
            return $component::getTable();
        }, $this->app->managers->catalog->getMvaComponents());

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
                    if ($insert && $aff = $managers[$table]->insertMany($insert, ['ignore' => true])) {
                        $this->app->container->logger->debug($aff . ' updated for attr="' . $table . '"');
                    }
                }
            })
            ->run();

        return true;
    }

    public function doInMongoTransfer(array $attrs = [])
    {
        $aff = 0;

        /** @var Mysql $rdbms */
        $rdbms = $this->app->container->db;

        /** @var Mongo $nosql */
        $nosql = $this->app->services->nosql;

        foreach (array_merge(
                     $this->app->managers->catalog->getMvaComponents(),
                     $this->app->managers->catalog->getSvaComponents()
                 ) as $class) {
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

            $affTmp = 0;

            (new WalkChunk2(1000))
                ->setFnGet(function ($lastId, $size) use ($rdbms, $pk, $table, $columns) {
                    $query = new Query(['params' => []]);
                    $query->text = implode(' ', [
                        'SELECT ' . implode(', ', array_map(function ($column) use ($rdbms, $table) {
                            return $rdbms->quote($column, $table);
                        }, $columns)),
                        'FROM ' . $rdbms->quote($table),
                        $lastId ? $rdbms->makeWhereSQL(new Expression($rdbms->quote($pk, $table) . ' > ?', $lastId), $query->params, $table) : '',
                        $rdbms->makeGroupSQL($pk, $query->params, $table),
                        $rdbms->makeOrderSQL([$pk => SORT_ASC], $query->params, $table),
                        $rdbms->makeLimitSQL(0, $size, $query->params)
                    ]);

                    return $rdbms->reqToArrays($query);
                })
                ->setFnDo(function ($items) use ($nosql, $entity, $pk, $table, &$affTmp) {
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

                    $affTmp += $nosql->insertMany($table, $items);

                    return ($last = array_pop($items)) ? $last[$pk] : false;
                })
                ->run();

            $this->output($table . ' aff=' . $affTmp);
            $aff += $affTmp;
        }

        return $aff;
    }

    public function doTransferMvaValues(array $fromToToItemId): int
    {
        $aff = 0;

        $db = $this->app->container->db;

        $mvaPkToTable = $this->app->managers->catalog->getMvaPkToTable();
        $mvaPkToTable['image_id'] = 'image';

        foreach ($mvaPkToTable as $pk => $table) {
            $linkTable = 'item_' . $table;

            foreach ($fromToToItemId as $fromItemId => $toItemId) {
                $query = new Query();
                $query->text = implode(' ', [
                    'INSERT IGNORE INTO ' . $db->quote($linkTable) . ' (' . $db->quote('item_id') . ', ' . $db->quote($pk) . ')',
                    'SELECT ' . $toItemId . ', ' . $db->quote($pk) . ' FROM ' . $db->quote($linkTable),
                    'WHERE ' . $db->quote('item_id') . ' = ' . $fromItemId
                ]);
                $query->placeholders = false;

                $aff += $db->req($query)->affectedRows();
            }
        }

        return $aff;
    }
}