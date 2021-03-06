<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Helper\WalkChunk;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Mysql\MysqlQueryExpression;
use SNOWGIRL_CORE\Mysql\MysqlQuery;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;
use SNOWGIRL_SHOP\Item\FixWhere;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;
use SNOWGIRL_SHOP\Entity\Term as TermEntity;
use SNOWGIRL_SHOP\Manager\Term as TermManager;

/**
 * @property App app
 */
class Attr extends Util
{
    public function doDeleteNonExistingItemsMva(FixWhere $fixWhere = null, array $params = [])
    {
        $aff = 0;

        $mysql = $this->app->container->mysql;

        $itemTable = $this->app->managers->items->getEntity()->getTable();
        $itemPk = $this->app->managers->items->getEntity()->getPk();

        foreach ($this->app->managers->catalog->getMvaComponents() as $class) {
            /** @var ItemAttrManager $manager */
            $manager = $this->app->managers->getByEntityClass($class);
            $table = $manager->getEntity()->getTable();
            $pk = $manager->getEntity()->getPk();

            $linkManager = $manager->getMvaLinkManager();
            $linkTable = $linkManager->getEntity()->getTable();

            $query = new MysqlQuery($params);
            $query->params = [];
            $query->text = implode(' ', [
                'DELETE ' . $mysql->quote('ia'),
                'FROM ' . $mysql->quote($linkTable) . ' ' . $mysql->quote('ia'),
                'LEFT JOIN ' . $mysql->quote($table) . ' ' . $mysql->quote('a') . ' USING (' . $mysql->quote($pk) . ')',
                'WHERE ' . $mysql->quote($pk, 'a') . ' IS NULL'
            ]);

            $affTmp1 = $mysql->req($query)->affectedRows();

            $this->output($affTmp1 . ' deleted from ' . $linkTable . ' [not exists in ' . $table . ']');

            $where = $fixWhere ? $fixWhere->get() : [];
            $where[] = new MysqlQueryExpression($mysql->quote($itemPk, $itemTable) . ' IS NULL');

            $query = new MysqlQuery($params);
            $query->params = [];
            $query->text = implode(' ', [
                'DELETE ' . $mysql->quote('ia'),
                'FROM ' . $mysql->quote($linkTable) . ' ' . $mysql->quote('ia'),
                'LEFT JOIN ' . $mysql->quote($itemTable) . ' USING (' . $mysql->quote($itemPk) . ')',
                $mysql->makeWhereSQL($where, $query->params, null, $query->placeholders)
            ]);

            $affTmp2 = $mysql->req($query)->affectedRows();

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
            $manager->setOrders([new MysqlQueryExpression('LENGTH(`name`) DESC'), 'name' => SORT_ASC]);
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
            $manager->setOrders([new MysqlQueryExpression('LENGTH(`name`) DESC'), 'name' => SORT_ASC]);
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

    public function doTransferMvaValues(array $fromToToItemId): int
    {
        $aff = 0;

        $mysql = $this->app->container->mysql;

        $mvaPkToTable = $this->app->managers->catalog->getMvaPkToTable();
        $mvaPkToTable['image_id'] = 'image';

        foreach ($mvaPkToTable as $pk => $table) {
            $linkTable = 'item_' . $table;

            foreach ($fromToToItemId as $fromItemId => $toItemId) {
                $query = new MysqlQuery();
                $query->text = implode(' ', [
                    'INSERT IGNORE INTO ' . $mysql->quote($linkTable) . ' (' . $mysql->quote('item_id') . ', ' . $mysql->quote($pk) . ')',
                    'SELECT ' . $toItemId . ', ' . $mysql->quote($pk) . ' FROM ' . $mysql->quote($linkTable),
                    'WHERE ' . $mysql->quote('item_id') . ' = ' . $fromItemId
                ]);
                $query->placeholders = false;

                $aff += $mysql->req($query)->affectedRows();
            }
        }

        return $aff;
    }
}