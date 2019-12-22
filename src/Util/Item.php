<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Helper\WalkChunk;
use SNOWGIRL_CORE\Helper\WalkChunk2;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Service\Rdbms;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_CORE\Service\Nosql\Mongo;
use SNOWGIRL_CORE\Service\Rdbms\Mysql;
use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_SHOP\App\Console as App;
use SNOWGIRL_SHOP\Catalog\SRC;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Entity\Item as ItemEntity;
use SNOWGIRL_SHOP\Item\FixWhere;
use SNOWGIRL_SHOP\Item\URI as ItemUri;
use SNOWGIRL_SHOP\Entity\Brand;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;
use SNOWGIRL_SHOP\Entity\Item\Archive as ItemArchive;

/**
 * Class Item
 *
 * @property App app
 * @package SNOWGIRL_SHOP\Util
 */
class Item extends Util
{
    public function doFixSeoNames()
    {
        $db = $this->app->services->rdbms;

        (new WalkChunk(1000))
            ->setFnGet(function ($page, $size) use ($db) {
                return $db->req(implode(' ', [
                    'SELECT ' . $db->quote('b') . '.*, COUNT(*) AS ' . $db->quote('cnt'),
                    'FROM ' . $db->quote(Brand::getTable()) . ' AS ' . $db->quote('b'),
                    'INNER JOIN ' . $db->quote(ItemEntity::getTable()) . ' AS ' . $db->quote('i') . ' USING(' . $db->quote(Brand::getPk()) . ')',
                    'GROUP BY ' . $db->quote(Brand::getPk(), 'b'),
                    'LIMIT ' . (($page - 1) * $size) . ', ' . $size
                ]))->reqToArrays();
            })
            ->setFnDo(function ($items) use ($db) {
                foreach ($items as $item) {
                    try {
                        $cnt = $item['cnt'];

                        /** @var Brand $brand */
                        $brand = $this->app->managers->brands->populateRow($item);

                        $currentName = $brand->getName();
                        $tmp = Brand::normalizeText($currentName);

                        if ($tmp != $currentName) {
                            $brand->setName($tmp);
                        }

                        $currentUri = $brand->getUri();
                        $tmp = Brand::normalizeUri($brand->getName());

                        if ($tmp != $currentUri) {
                            $brand->setUri($tmp);
                        }

                        if ($brand->isAttrsChanged()) {
                            $this->output('Changing ' . $brand->getId() . '...');

                            if ($cnt > 0) {
                                $this->output('Brand is not empty...');

                                $isNeedUpdate = false;
                                $isNeedDelete = false;

                                if ($currentUri != $brand->getUri()) {
                                    $this->output('...trying to update ' . $db->quote(Brand::getPk()) . ' that relates to our new ' . $db->quote('uri') . '...');

                                    $aff = $db->req(implode(' ', [
                                        'UPDATE ' . $db->quote(ItemEntity::getTable()),
                                        'SET ' . $db->quote(Brand::getPk()) . ' =',
                                        '(SELECT ' . $db->quote(Brand::getPk()),
                                        'FROM ' . $db->quote(Brand::getTable()),
                                        'WHERE ' . $db->quote('uri') . ' = \'' . $brand->getUri() . '\')',
                                        'WHERE ' . $db->quote(Brand::getPk()) . ' = ' . $brand->getId()
                                    ]))->affectedRows();

                                    $this->output('...' . $aff . ' affected');

                                    if ($aff > 0) {
                                        $isNeedDelete = true;
                                    } else {
                                        $isNeedUpdate = true;
                                    }
                                } else {
                                    $isNeedUpdate = true;
                                }

                                if ($currentName != $brand->getName()) {
                                    $this->output('...trying to update ' . $db->quote(Brand::getPk()) . ' that relates to our new ' . $db->quote('name') . '...');

                                    $aff = $db->req(implode(' ', [
                                        'UPDATE ' . $db->quote(ItemEntity::getTable()),
                                        'SET ' . $db->quote(Brand::getPk()) . ' =',
                                        '(SELECT ' . $db->quote(Brand::getPk()),
                                        'FROM ' . $db->quote(Brand::getTable()),
                                        'WHERE ' . $db->quote('name') . ' = \'' . $brand->getName() . '\')',
                                        'WHERE ' . $db->quote(Brand::getPk()) . ' = ' . $brand->getId()
                                    ]))->affectedRows();

                                    $this->output('...' . $aff . ' affected');

                                    if ($aff > 0) {
                                        $isNeedDelete = true;
                                    } else {
                                        $isNeedUpdate = true;
                                    }
                                }

                                if ($isNeedUpdate && !$isNeedDelete) {
                                    $this->app->managers->brands->updateOne($brand);
                                    $this->output('Brand is updated...');
                                } elseif ($isNeedDelete && !$isNeedUpdate) {
                                    $this->app->managers->brands->deleteOne($brand);
                                    $this->output('...brand is deleted');
                                }
                            } else {
                                if ($currentName != $brand->getName()) {
                                    $this->output($currentName . ' -> ' . $brand->getName());
                                }

                                if ($currentUri != $brand->getUri()) {
                                    $this->output($currentUri . ' -> ' . $brand->getUri());
                                }

                                $this->app->managers->brands->updateOne($brand);

                                $this->output('Brand is updated...');
                            }
                        }
                        //else {
                        //@todo...
                        //}
                    } catch (Exception $ex) {
                        $this->app->services->logger->makeException($ex);
                    }
                }
            })
            ->run();

        return true;
    }

    public function doUpdateItemsOrders()
    {
        $table = $this->app->managers->items->getEntity()->getTable();
        $pk = $this->app->managers->items->getEntity()->getPk();
        $db = $this->app->services->rdbms;
        $after = 'is_in_stock';

        foreach (SRC::getOrderValues() as $order) {
            $uri = new URI([URI::ORDER => $order]);
            $src = $uri->getSRC();

            $info = $src->getOrderInfo();

            if (!in_array($info->cache_column, $db->getColumns($table))) {
                $db->debugReq(implode(' ', [
                    'ALTER TABLE' . ' ' . $db->quote($table),
                    'ADD COLUMN ' . $db->quote($info->cache_column) . ' int(11) NOT NULL DEFAULT \'0\'',
                    'AFTER ' . $db->quote($after)
                ]));
            }

            $db->req('SET @num=0');

            $query = new Query(['params' => []]);
            $query->text = implode(' ', [
                'UPDATE ' . $db->quote($table) . ' AS ' . $db->quote('i'),
                'INNER JOIN (',
                'SELECT ' . $db->quote($pk) . ', @num:=@num+1 AS ' . $db->quote('num'),
                'FROM ' . $db->quote($table),
                $db->makeOrderSQL($src->getDataProvider('mysql')->getOrder(), $query->params),
                ') AS ' . $db->quote('i2') . ' USING(' . $db->quote($pk) . ')',
                'SET ' . $db->quote($info->cache_column, 'i') . ' = ' . $db->quote('num', 'i2')
            ]);

            $aff = $db->debugReq($query)->affectedRows();

            $after = $info->cache_column;

            $this->output('DONE[' . $info->cache_column . ']: ' . $aff);
        }

        $this->output('DONE');

        return true;
    }

    public function doDeleteWithNonExistingCategories(FixWhere $fixWhere = null)
    {
        $db = $this->app->services->rdbms;
        $it = $this->app->managers->items->getEntity()->getTable();
        $ck = $this->app->managers->categories->getEntity()->getPk();
        $ct = $this->app->managers->categories->getEntity()->getTable();

        $where = $fixWhere ? $fixWhere->get() : [];
        $where[] = new Expr($db->quote($ck, $ct) . ' IS NULL');

        $query = new Query(['params' => []]);
        $query->text = implode(' ', [
            'DELETE ' . $db->quote($it),
            'FROM ' . $db->quote($it),
            'LEFT JOIN ' . $db->quote($ct) . ' ON ' . $db->quote($ck, $ct) . ' = ' . $db->quote($ck, $it),
            $db->makeWhereSQL($where, $query->params)
        ]);

        return $this->app->services->rdbms->req($query)->affectedRows();
    }

    public function doDeleteWithNonExistingBrands(FixWhere $fixWhere = null)
    {
        $db = $this->app->services->rdbms;
        $it = $this->app->managers->items->getEntity()->getTable();
        $bk = $this->app->managers->brands->getEntity()->getPk();
        $bt = $this->app->managers->brands->getEntity()->getTable();

        $where = $fixWhere ? $fixWhere->get() : [];
        $where[] = new Expr($db->quote($bk, $bt) . ' IS NULL');

        $query = new Query(['params' => []]);
        $query->text = implode(' ', [
            'DELETE ' . $db->quote($it),
            'FROM ' . $db->quote($it),
            'LEFT JOIN ' . $db->quote($bt) . ' ON ' . $db->quote($bk, $bt) . ' = ' . $db->quote($bk, $it),
            $db->makeWhereSQL($where, $query->params)
        ]);

        return $this->app->services->rdbms->req($query)->affectedRows();
    }

    public function doFixWithNonExistingCountries(FixWhere $fixWhere)
    {
        $db = $this->app->services->rdbms;
        $pk = $this->app->managers->countries->getEntity()->getPk();

        $id = $this->app->managers->countries->getList($pk);

        $where = $fixWhere->get();
        $where[] = new Expr($db->quote($pk) . ' NOT IN (' . implode(',', $id) . ')');

        return $this->app->managers->items->updateMany([$pk => null], $where);
    }

    public function doFixWithNonExistingAttrs(FixWhere $fixWhere)
    {
        $aff = $this->doDeleteWithNonExistingCategories($fixWhere);
        $this->output('deleted with invalid categories: ' . $aff);

        $tmp = $this->doDeleteWithNonExistingBrands($fixWhere);
        $this->output('deleted with invalid brands: ' . $tmp);
        $aff += $tmp;

        $tmp = $this->doFixWithNonExistingCountries($fixWhere);
        $this->output('updated with invalid countries: ' . $tmp);
        $aff += $tmp;

        return $aff;
    }

    protected $archiveIgnore = [
        'rating',
        'is_active',

        'order_desc_relevance',
        'order_desc_rating',
        'order_asc_price',
        'order_desc_price'
    ];

    public function doCreateArchiveTable()
    {
        $db = $this->app->services->rdbms;

        $itemTable = $this->app->managers->items->getEntity()->getTable();
        $showCreate = $db->showCreateTable($itemTable);

        foreach ($this->archiveIgnore as $column) {
            $showCreate = preg_replace('/' . $db->quote($column) . ' [^\,]+\,/', '', $showCreate);
        }

        $showCreate = preg_replace('/[\r\n]  (UNIQUE )?KEY `[^`]+` \([^\(]+\),?/', '', $showCreate);
        $showCreate = preg_replace('/ COMMENT=\'[^\']+\'/', '', $showCreate);

        $showCreate = str_replace($db->quote($itemTable), $db->quote(ItemEntity\Archive::getTable()), $showCreate);

        $mva = [];

        foreach ($this->app->managers->catalog->getMvaComponents() as $entity) {
            $mva[] = $db->quote($entity::getPk()) . ' VARCHAR(128) DEFAULT NULL';
        }

        $mva = implode(",\r\n", $mva);

        $showCreate = str_replace('), ENGINE=', '), ' . $mva . ') ENGINE=', $showCreate);

        $db->req($showCreate);

        return true;
    }

    public function doInArchiveTransfer(array $where): int
    {
        $aff = 0;

        $this->doCreateArchiveTable();

        $db = $this->app->services->rdbms;

        $itemTable = $this->app->managers->items->getEntity()->getTable();
        $itemPk = $this->app->managers->items->getEntity()->getPk();

        $archiveTable = $this->app->managers->archiveItems->getEntity()->getTable();
        $archiveColumns = $db->getColumns($archiveTable);

        $mva = $this->app->managers->catalog->getMvaPkToTable();
        $mva['image_id'] = 'image';

        $query = new Query(['params' => []]);
        $query->text = implode(' ', [
            'INSERT IGNORE INTO',
            $db->quote($archiveTable),
            '(' . implode(', ', array_map(function ($column) use ($db) {
                return $db->quote($column);
            }, $archiveColumns)) . ')',
            'SELECT ' . implode(', ', array_map(function ($column) use ($db, $mva, $itemTable) {
                if (isset($mva[$column])) {
                    $table = 'item_' . $mva[$column];
                    return 'GROUP_CONCAT(' . $db->quote($column, $table) . ')';
                }

                if ('is_in_stock' == $column) {
                    return 0;
                }

                return $db->quote($column, $itemTable);
            }, $archiveColumns)),
            'FROM ' . $db->quote($itemTable),
            implode(' ', array_map(function ($table) use ($db, $itemTable, $itemPk) {
                $table = 'item_' . $table;
                return 'LEFT JOIN ' . $db->quote($table) . ' ON' . $db->quote($itemPk, $itemTable) . ' = ' . $db->quote($itemPk, $table);
            }, $mva)),
            $db->makeWhereSQL($where, $query->params, $itemTable),
            $db->makeGroupSQL($itemPk, $query->params, $itemTable),
        ]);

        $affTmp = $db->req($query)->affectedRows();
        $this->output('Copied to item_archive: ' . $affTmp);

        $aff += $affTmp;

        foreach ($mva as $pk => $table) {
            $table = 'item_' . $table;

            $query = new Query(['params' => []]);
            $query->text = implode(' ', [
                'DELETE ' . $db->quote($table),
                'FROM ' . $db->quote($table),
                'INNER JOIN ' . $db->quote($itemTable) . ' USING(' . $db->quote($itemPk) . ')',
                $db->makeWhereSQL($where, $query->params, $itemTable)
            ]);

            $affTmp = $db->req($query)->affectedRows();
            $this->output('Deleted from ' . $table . ': ' . $affTmp);

            $aff += $affTmp;
        }

        $affTmp = $this->app->managers->items->deleteMany($where);
        $this->output('Deleted from item: ' . $affTmp);

        $aff += $affTmp;

        return $aff;
    }

    public function doOutArchiveTransfer(array $where): int
    {
        $aff = 0;

        $db = $this->app->services->rdbms;

        $itemTable = $this->app->managers->items->getEntity()->getTable();
        $itemPk = $this->app->managers->items->getEntity()->getPk();

        $archiveTable = $this->app->managers->archiveItems->getEntity()->getTable();
        $archiveColumns = $db->getColumns($archiveTable);

        $mva = $this->app->managers->catalog->getMvaPkToTable();
        $mva['image_id'] = 'image';

        $tmpArchiveColumns = array_diff($archiveColumns, array_keys($mva));

        $query = new Query(['params' => []]);
        $query->text = implode(' ', [
            'INSERT IGNORE INTO',
            $db->quote($itemTable) . ' (' . implode(', ', array_map(function ($column) use ($db) {
                return $db->quote($column);
            }, $tmpArchiveColumns)) . ')',
            '(',
            'SELECT ' . implode(', ', array_map(function ($column) use ($db) {
                if ('is_in_stock' == $column) {
                    return 0;
                }

                return $db->quote($column);
            }, $tmpArchiveColumns)),
            'FROM ' . $db->quote($archiveTable),
            $db->makeWhereSQL($where, $query->params),
            ')'
        ]);

        $affTmp = $db->req($query)->affectedRows();
        $this->output('Copied to item: ' . $affTmp);

        $aff += $affTmp;

        $db->dropTable('numbers');
        $db->createTable('numbers', [
            ($qv = $db->quote('value')) . ' TINYINT(1) UNSIGNED NOT NULL',
            'PRIMARY KEY (' . $qv . ')'
        ], 'MyISAM');

        $db->insertMany('numbers', array_map(function ($value) {
            return ['value' => $value];
        }, range(1, 5)));

        foreach ($mva as $pk => $table) {
            $affTmp = $db->req(implode(' ', [
                'INSERT IGNORE INTO',
                $db->quote('item_' . $table),
                '(' . $db->quote($itemPk) . ', ' . $db->quote($pk) . ')',
                'SELECT ' . $db->quote($itemPk) . ', SUBSTRING_INDEX(SUBSTRING_INDEX(' . $db->quote($pk) . ', \',\', ' . $db->quote('value') . '), \',\', -1)',
                'FROM ' . $db->quote('numbers'),
                'INNER JOIN ' . $db->quote($archiveTable) . ' ON CHAR_LENGTH(' . $db->quote($pk) . ') - CHAR_LENGTH(REPLACE(' . $db->quote($pk) . ', \',\', \'\')) >= ' . $db->quote('value') . ' - 1',
                'ORDER BY ' . $db->quote($itemPk) . ', ' . $db->quote($pk)
            ]))->affectedRows();
            $this->output('Copied to item_' . $table . ': ' . $affTmp);

            $aff += $affTmp;
        }

        $this->app->managers->archiveItems->deleteMany($where);
        $this->output('Deleted from item_archive: ' . $affTmp);

        $aff += $affTmp;

        return $aff;
    }

    public function doFixArchiveMvaValues()
    {
        $aff = 0;

        $entity = $this->app->managers->archiveItems->getEntity();

        $itemPk = $entity->getPk();

        $mva = $this->app->managers->catalog->getMvaPkToTable();

        $mvaIds = [];

        foreach ($mva as $pk => $table) {
            $mvaIds[$pk] = $this->app->managers->getByTable($table)->getList();
        }

        $mysql = $this->app->storage->mysql;

        (new WalkChunk2(1000))
            ->setFnGet(function ($lastId, $size) use ($mysql, $itemPk) {
                $where = [];

                if ($lastId) {
                    $where[] = new Expr($mysql->quote($itemPk) . ' > ?', $lastId);
                }

                return $this->app->managers->archiveItems
                    ->setWhere($where)
                    ->setOrders([$itemPk => SORT_ASC])
                    ->setLimit($size)
                    ->getObjects();
            })
            ->setFnDo(function ($items) use ($itemPk, $mva, $mvaIds, &$aff) {
                /** @var ItemArchive[] $items */

                foreach ($items as $item) {
                    foreach ($mva as $pk => $table) {
                        $ids = explode(',', $item->get($pk));
                        $ids = array_map('intval', $ids);
                        $ids = array_unique($ids);
                        $possibleIds = $mvaIds[$pk];
                        $ids = array_filter($ids, function ($id) use ($possibleIds) {
                            return $id && in_array($id, $possibleIds);
                        });
                        $item->set($pk, $ids ? implode(',', $ids) : null);
                    }

                    $aff += $this->app->managers->archiveItems->updateOne($item) ? 1 : 0;
                }

                return isset($item) ? $item->getId() : false;
            })
            ->run();

        return $aff;
    }

    /**
     * @return bool
     */
    public function doAddImportSourceId()
    {
        $aff = 0;

        $this->output('items total count: ' . $this->app->managers->items->getCount());

        foreach ($this->app->managers->sources->getObjects() as $source) {
            $aff += $this->app->managers->items->updateMany(
                ['import_source_id' => $source->getId()],
                ['vendor_id' => $source->getVendorId()]
            );
        }

        $this->output('DONE[aff=' . $aff . ']');

        return true;
    }

    /**
     * @return bool
     */
    public function doAddArchiveImportSourceId()
    {
        $aff = 0;

        $this->output('archive items total count: ' . $this->app->managers->archiveItems->getCount());

        foreach ($this->app->managers->sources->getObjects() as $source) {
            $aff += $this->app->managers->archiveItems->updateMany(
                ['import_source_id' => $source->getId()],
                ['vendor_id' => $source->getVendorId()]
            );
        }

        $this->output('DONE[aff=' . $aff . ']');

        return true;
    }

    public function doFixDuplicates($importSourceId)
    {
        $db = $this->app->services->rdbms;
        $table = $this->app->managers->items->getEntity()->getTable();
        $pk = $this->app->managers->items->getEntity()->getPk();

        (new WalkChunk(1000))
            ->setFnGet(function ($page, $size) use ($importSourceId, $db, $table, $pk) {
                return $db->req(implode(' ', [
                    'SELECT ' . implode(', ', [
                        $db->quote('image'),
                        'GROUP_CONCAT(' . $db->quote($pk) . ') AS ' . $db->quote($pk),
                        'GROUP_CONCAT(' . $db->quote('name') . ') AS ' . $db->quote('name'),
                        'COUNT(*) AS ' . $db->quote('cnt')
                    ]),
                    'FROM ' . $db->quote($table),
                    'WHERE ' . $db->quote('import_source_id') . ' = ' . $importSourceId,
                    'GROUP BY ' . $db->quote('image'),
                    'HAVING ' . $db->quote('cnt') . ' > 1',
//                    'LIMIT ' . (($page - 1) * $size) . ', ' . $size
                    'LIMIT ' . $size
                ]))->reqToArrays();
            })
            ->setFnDo(function ($rows) use ($db, $pk) {
                $insert = [];
                $delete = [];

                foreach ($rows as $row) {
                    $itemId = explode(',', $row[$pk]);
                    $name = explode(',', $row['name']);

                    $id = array_shift($itemId);
                    array_shift($name);

                    foreach ($itemId as $i => $itemId2) {
                        $uri = $this->app->router->makeLink('item', ['uri' => ItemUri::buildPath($name[$i], $itemId[$i])], 'master');
                        $this->output($uri . ' going to be deleted...');
                    }

                    $insert = array_merge($insert, array_fill(0, count($itemId), $id));
                    $delete = array_merge($delete, $itemId);
                }

                $insert = array_chunk($insert, 1000);
                $delete = array_chunk($delete, 1000);

                foreach (array_keys($insert) as $i) {
                    $aff = $this->app->managers->itemRedirects->insertMany(array_map(function ($insert, $delete) {
                        return [
                            'id_from' => $delete,
                            'id_to' => $insert
                        ];
                    }, $insert[$i], $delete[$i]));

                    $this->output($aff . ' item redirects created');

                    $aff = $this->app->managers->items->deleteMany([$pk => $delete[$i]]);

                    $this->output($aff . ' items deleted');

                    foreach ($this->app->managers->catalog->getMvaComponents() as $class) {
                        $aff = $this->app->managers->getByTable($class::getTable())->getMvaLinkManager()
                            ->deleteMany(['item_id' => $delete[$i]]);

                        $this->output($aff . ' ' . $class::getTable() . ' attrs deleted');
                    }
                }
            })
            ->run();

        return true;
    }

    public function doDeleteItemsWithInvalidCategories(FixWhere $fixWhere = null)
    {
        if ($categoryIds = $this->app->managers->categories->clear()->getList()) {
            $where = $fixWhere ? $fixWhere->get() : [];
            $where[] = new Expr($this->app->services->rdbms->quote('category_id') . ' NOT IN (' . implode(', ', $categoryIds) . ')');

            $aff = $this->app->managers->items->deleteMany($where);

            if ($isOk = $aff > 0) {
                $this->app->services->logger->make('There are were ' . $aff . 'items with invalid categories... Deleted...');
            }

            return $isOk;
        }

        return true;
    }

    public function doDeleteItemsWithInvalidBrands(FixWhere $fixWhere = null)
    {
        //@todo...
        return true;
    }

    /**
     * @todo to run this without vendor_id or time intervals - need to create indexes (!)
     * @todo already created:
     * [category_id,    vendor_id, created_at, updated_at]
     * [                vendor_id, created_at, updated_at]
     *
     * @param FixWhere|null $fixWhere
     *
     * @return bool
     */
    public function doFixItemsCategories(FixWhere $fixWhere = null)
    {
        $manager = $this->app->managers->categoriesToEntities;

        $manager->generate();

//        $time = time();

        //the highest priority
        $manager->updateByParentsAndEntities($fixWhere);

        if (!$fixWhere) {
            $fixWhere = new FixWhere($this->app);
        }

        // do not touch those which were changed on previous call
//        $fixWhere->setUpdatedAtTo($time, true);
        $fixWhere->setUpdatedAtIsNull(true);

//        $manager->updateByParentsAndNamesLikeCategories($fixWhere);

//        $manager->updateByEntities($fixWhere);

//        $manager->updateByEntitiesLikeEntities($fixWhere);
//        $manager->updateByNamesLikeEntities($fixWhere);

        $manager->updateByParentsAndNamesLikeEntities($fixWhere);

        //update for admin
        $manager->generate(true);

        return true;
    }

    public function doTransferByAttrs($source, $target)
    {
        return $this->app->services->rdbms->makeTransaction(function (Rdbms $db) use ($target, $source) {
            $affGlobal = 0;

            $mva = Manager::mapEntitiesAddPksAsKeys($this->app->managers->catalog->getMvaComponents());

            $targetSva = array_filter($target, function ($attrPk) use ($mva) {
                return !isset($mva[$attrPk]);
            }, ARRAY_FILTER_USE_KEY);

            $sourceMva = array_filter($source, function ($attrPk) use ($mva) {
                return isset($mva[$attrPk]);
            }, ARRAY_FILTER_USE_KEY);

            $targetMva = array_filter($target, function ($attrPk) use ($mva) {
                return isset($mva[$attrPk]);
            }, ARRAY_FILTER_USE_KEY);

            (new WalkChunk(1000))
                ->setFnGet(function ($page, $size) use ($source, $db) {
                    $tmp = $source;
                    $tmp[URI::EVEN_NOT_STANDARD_PER_PAGE] = 1;
                    $tmp[URI::PAGE_NUM] = $page;
                    $tmp[URI::PER_PAGE] = $size;

                    return (new URI($tmp))->getSRC()->getItemsId();
                })
                ->setFnDo(function ($items) use ($sourceMva, $targetSva, $targetMva, $mva, &$affGlobal) {
                    //update item table
                    $aff = $this->app->managers->items->updateMany($targetSva, [
                        ItemEntity::getPk() => $items
                    ]);

                    $affGlobal += $aff;

                    $this->output('Affected item table update: ' . $aff);

                    //update item_<attr> tables
                    //..delete old source records  (?)
                    foreach ($sourceMva as $attrPk => $attrId) {
                        /** @var Entity $linkAttrEntity */
                        $linkAttrEntity = ItemAttrManager::makeLinkTableNameByEntityClass($mva[$attrPk]);

                        $aff = $this->app->managers->getByEntityClass($linkAttrEntity)->deleteMany([
                            'item_id' => $items,
                            $attrPk => $attrId
                        ]);

                        $affGlobal += $aff;

                        $this->output('Affected ' . $linkAttrEntity::getTable() . ' table delete: ' . $aff);
                    }

                    //..insert new ones
                    foreach ($targetMva as $attrPk => $attrId) {
                        /** @var Entity $linkAttrEntity */
                        $linkAttrEntity = ItemAttrManager::makeLinkEntityClassByAttrEntityClass($mva[$attrPk]);

                        foreach (is_array($attrId) ? $attrId : [$attrId] as $attrId2) {
                            $attrId2 = (int)$attrId2;

                            $aff = $this->app->managers->getByEntityClass($linkAttrEntity)->insertMany(array_map(function ($item) use ($attrPk, $attrId2) {
                                return [
                                    'item_id' => $item,
                                    $attrPk => $attrId2
                                ];
                            }, $items));

                            $affGlobal += $aff;

                            $this->output('Affected ' . $linkAttrEntity::getTable() . ' table insert: ' . $aff);
                        }
                    }
                })
                ->run();

            //update page_catalog
            //@todo improve...
            $aff = $this->app->managers->catalog->updateMany($target, $source, true);

            $affGlobal += $aff;

            $this->output('Affected ' . $this->app->managers->catalog->getEntity()->getTable() . ' table insert: ' . $aff);

            return $affGlobal;
        });
    }

    /**
     * @return bool|int
     * @throws \Exception
     */
    public function doInMongoTransfer()
    {
        /** @var Mysql $rdbms */
        $rdbms = $this->app->services->rdbms;

        /** @var Mongo $nosql */
        $nosql = $this->app->services->nosql;

//        $nosql->dropSchema($nosql->getSchema());

        /** @var string|Entity $entity */
        $entity = $this->app->managers->items->getEntity();

        $table = $entity->getTable();
        $pk = $entity->getPk();

        if (!$nosql->createCollection($table)) {
            return false;
        }

        $sva = $this->app->managers->catalog->getSvaPkToTable();
        $mva = $this->app->managers->catalog->getMvaPkToTable();

        $columns = array_merge(array_keys($entity->getColumns()), array_keys($mva));

        $aff = 0;

        (new WalkChunk2(1000))
            ->setFnGet(function ($lastId, $size) use ($rdbms, $pk, $table, $mva, $columns) {
                $query = new Query(['params' => []]);
                $query->text = implode(' ', [
                    'SELECT ' . implode(', ', array_map(function ($column) use ($rdbms, $mva, $table) {
                        if (array_key_exists($column, $mva)) {
                            return 'GROUP_CONCAT(DISTINCT ' . $rdbms->quote($column, 'item_' . $mva[$column]) . ') AS ' . $rdbms->quote($column);
                        }

                        return $rdbms->quote($column, $table);
                    }, $columns)),
                    'FROM ' . $rdbms->quote($table) . ' ' . implode(' ', array_map(function ($attrTable) use ($rdbms, $table, $pk) {
                        $linkTable = 'item_' . $attrTable;
                        return 'LEFT JOIN ' . $rdbms->quote($linkTable) . ' ON' . $rdbms->quote($pk, $table) . ' = ' . $rdbms->quote($pk, $linkTable);
                    }, $mva)),
                    $lastId ? $rdbms->makeWhereSQL(new Expr($rdbms->quote($pk, $table) . ' > ?', $lastId), $query->params, $table) : '',
//                    $rdbms->makeWhereSQL(['item_id' => 309018], $query->params, $table),
                    $rdbms->makeGroupSQL($pk, $query->params, $table),
                    $rdbms->makeOrderSQL([$pk => SORT_ASC], $query->params, $table),
                    $rdbms->makeLimitSQL(0, $size, $query->params)
                ]);

//                print_r($query->text);
//                die;

                return $rdbms->req($query)->reqToArrays();
            })
            ->setFnDo(function ($items) use ($nosql, $entity, $pk, $table, $sva, $mva, &$aff) {
                $items = array_map(function ($item) use ($pk, $sva, $mva, $entity) {
                    $item = array_filter($item, function ($v) {
                        return null !== $v;
                    });

                    $item['_id'] = $item[$pk];

                    foreach ($entity->getColumns() as $column => $options) {
                        if (isset($item[$column])) {
                            switch ($options['type']) {
                                case Entity::COLUMN_FLOAT:
                                    if ($item[$column]) {
                                        $item[$column] = (float)$item[$column];
                                    } else {
                                        $item[$column] = $options['default'];
                                    }
                                    break;
                                case Entity::COLUMN_INT:
                                    if ($item[$column]) {
                                        $item[$column] = (int)$item[$column];
                                    } else {
                                        $item[$column] = $options['default'];
                                    }
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

                    foreach ($sva as $pk => $table) {
                        if (isset($item[$pk])) {
                            if ($item[$pk]) {
                                $item[$pk] = (int)$item[$pk];
                            } else {
                                unset($item[$pk]);
//                            $item[$pk] = null;
                            }
                        }
                    }

                    foreach ($mva as $pk => $table) {
                        if (isset($item[$pk])) {
                            if ($item[$pk]) {
                                $item[$pk] = array_map('intval', explode(',', $item[$pk]));
                            } else {
                                unset($item[$pk]);
//                            $item[$pk] = null;
//                            $item[$pk] = [];
                            }
                        }
                    }

                    return $item;
                }, $items);

                $aff += $nosql->insertMany($table, $items);
//                die;

                return ($last = array_pop($items)) ? $last[$pk] : false;
            })
            ->run();

        return $aff;
    }

    public function doIndexFtdbms(int $reindexDays = 0)
    {
        return $this->doIndexElastic(new Expr(implode(' OR ', [
            $this->app->storage->mysql->quote('created_at') . ' >= (CURDATE() - INTERVAL ? DAY)',
            $this->app->storage->mysql->quote('updated_at') . ' >= (CURDATE() - INTERVAL ? DAY)',
        ]), $reindexDays, $reindexDays));
    }

    protected function getSearchColumns(): array
    {
        return $this->app->managers->items->findColumns(Entity::SEARCH_IN);
    }

    protected function getAjaxSuggestionsAttrPkToTable(): array
    {
        return [
            ($entity = $this->app->managers->brands->getEntity())->getPk() => $entity->getTable(),
            ($entity = $this->app->managers->colors->getEntity())->getPk() => $entity->getTable(),
            ($entity = $this->app->managers->materials->getEntity())->getPk() => $entity->getTable(),
        ];
    }

    protected function getElasticMappings(): array
    {
        $properties = [
            'is_sport' => 'boolean',
            'is_size_plus' => 'boolean',
            'is_sales' => 'boolean',
            'price' => 'integer',

            'order_desc_relevance' => 'integer',
            'order_desc_rating' => 'integer',
            'order_asc_price' => 'integer',
            'order_desc_price' => 'integer'
        ];

        foreach ($this->getSearchColumns() as $column) {
            $properties[$column] = 'text';
            $properties[$column . '_length'] = 'short';
        }

        foreach ($properties as $column => &$type) {
            $type = ['type' => $type];
        }

        $sva = $this->app->managers->catalog->getSvaPkToTable();
        $mva = $this->app->managers->catalog->getMvaPkToTable();

        foreach ($this->getAjaxSuggestionsAttrPkToTable() as $pk => $table) {
            if (isset($sva[$pk])) {
                $properties[$table] = ['type' => 'object'];
                unset($sva[$pk]);
            } elseif (isset($mva[$pk])) {
                $properties[$table] = ['type' => 'nested'];
                unset($mva[$pk]);
            }

            $properties[$table]['properties'] = [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'text']
            ];
        }

        foreach (array_keys($sva) as $pk) {
            $properties[$pk] = ['type' => 'integer'];
        }

        foreach (array_keys($mva) as $pk) {
            $properties[$pk] = ['type' => 'integer'];
        }

        return ['properties' => $properties];
    }

    public function doCreateElasticIndex(string $index = null): bool
    {
        $index = $index ?: $this->app->managers->items->getEntity()->getTable();

        $elastic = $this->app->storage->elastic;

        $elastic->deleteIndex($index);

        if (!$elastic->createIndex($index, $this->getElasticMappings())) {
            return false;
        }

        return true;
    }

    public function doRawIndexElastic(string $index = null, $where = null): int
    {
        $aff = 0;

        $elastic = $this->app->storage->elastic;

        /** @var string|Entity $entity */
        $entity = $this->app->managers->items->getEntity();

        $itemTable = $entity->getTable();
        $itemPk = $entity->getPk();

        $index = $index ?: $itemTable;
        $where = Arrays::cast($where);

        $columns = [
            $itemPk,
            'is_sport',
            'is_size_plus',
            'old_price',
            'price',

            'order_desc_relevance',
            'order_desc_rating',
            'order_asc_price',
            'order_desc_price'
        ];

        $searchColumns = $this->getSearchColumns();

        foreach ($searchColumns as $column) {
            $columns[] = $column;
        }

        $sva = $this->app->managers->catalog->getSvaPkToTable();

        foreach (array_keys($sva) as $pk) {
            $columns[] = $pk;
        }

        $mva = $this->app->managers->catalog->getMvaPkToTable();

        foreach (array_keys($mva) as $pk) {
            $columns[] = $pk;
        }

        $columnsOptions = Arrays::filterByKeysArray($entity->getColumns(), $columns);

        $categoryManager = $this->app->managers->categories;

        /** @var Manager[] $managers */
        $managers = [];

        //which attrs name to inject into index
        $injectAttrIdToName = [];

        foreach ($this->getAjaxSuggestionsAttrPkToTable() as $attrPk => $attrTable) {
            /** @var Manager $attrManager */
            $attrManager = $this->app->managers->getByTable($attrTable);
            $entity = $attrManager->getEntity();
            $injectAttrIdToName[$attrPk] = $this->app->utils->attrs->getIdToName($entity->getClass(), true);
            $managers[$attrPk] = $attrManager;
        }

        $mappingKeys = array_keys($this->getElasticMappings()['properties']);

        $mysql = $this->app->storage->mysql;

        (new WalkChunk2(1000))
            ->setFnGet(function ($lastId, $size) use ($mysql, $itemPk, $itemTable, $mva, $columns, $where) {
                $query = new Query(['params' => []]);

                if ($lastId) {
                    $where[] = new Expr($mysql->quote($itemPk, $itemTable) . ' > ?', $lastId);
                }

                $query->text = implode(' ', [
                    'SELECT ' . implode(', ', array_map(function ($column) use ($mysql, $mva, $itemTable) {
                        if (array_key_exists($column, $mva)) {
                            return 'GROUP_CONCAT(DISTINCT ' . $mysql->quote($column, 'item_' . $mva[$column]) . ') AS ' . $mysql->quote($column);
                        }

                        return $mysql->quote($column, $itemTable);
                    }, $columns)),
                    'FROM ' . $mysql->quote($itemTable) . ' ' . implode(' ', array_map(function ($attrTable)
                    use ($mysql, $itemTable, $itemPk) {
                        $linkTable = 'item_' . $attrTable;
                        return 'LEFT JOIN ' . $mysql->quote($linkTable) . ' ON' . $mysql->quote($itemPk, $itemTable) . ' = ' . $mysql->quote($itemPk, $linkTable);
                    }, $mva)),
                    $where ? $mysql->makeWhereSQL($where, $query->params, $itemTable) : '',
//                    $mysql->makeWhereSQL(['item_id' => 309018], $query->params, $itemTable),
                    $mysql->makeGroupSQL($itemPk, $query->params, $itemTable),
                    $mysql->makeOrderSQL([$itemPk => SORT_ASC], $query->params, $itemTable),
                    $mysql->makeLimitSQL(0, $size, $query->params)
                ]);

                return $mysql->req($query)->reqToArrays();
            })
            ->setFnDo(function ($items) use (
                $elastic, $entity, $itemPk, $index, $sva, $mva, $columnsOptions, $mappingKeys, $categoryManager,
                $injectAttrIdToName, $managers, $searchColumns, &$aff
            ) {
                $items = Arrays::mapByKeyValueMaker($items, function ($i, $item) use (
                    $itemPk, $sva, $mva, $columnsOptions, $categoryManager, $injectAttrIdToName, $managers, $mappingKeys,
                    $searchColumns
                ) {
                    $item = array_filter($item, function ($v) {
                        return null !== $v;
                    });

                    foreach ($columnsOptions as $column => $options) {
                        if (isset($item[$column])) {
                            switch ($options['type']) {
                                case Entity::COLUMN_FLOAT:
                                    if ($item[$column]) {
                                        $item[$column] = (float)$item[$column];
                                    } else {
                                        $item[$column] = $options['default'];
                                    }
                                    break;
                                case Entity::COLUMN_INT:
                                    if ($item[$column]) {
                                        $item[$column] = (int)$item[$column];
                                    } else {
                                        $item[$column] = $options['default'];
                                    }
                                    break;
                                case Entity::COLUMN_TIME:
                                    if ($item[$column]) {
                                    } else {
                                        $item[$column] = $options['default'];
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                    }

                    if (isset($item['old_price'])) {
                        $item['is_sales'] = $item['old_price'] > 0 ? 1 : 0;
                        unset($item['old_price']);
                    } else {
                        $item['is_sales'] = 0;
                    }

                    foreach ([
                                 'is_sport',
                                 'is_size_plus',
                                 'is_sales',
                             ] as $column) {
                        if (isset($item[$column])) {
                            $item[$column] = 1 == $item[$column];
                        }
                    }

                    foreach ($sva as $pk => $table) {
                        if (isset($item[$pk])) {
                            if ($item[$pk]) {
                                $item[$pk] = (int)$item[$pk];

//                                if ('category_id' == $pk) {
//                                    $item[$pk] = $categoryManager->getChildrenIdFor($item[$pk]);
//                                    $item['category_ids'] = $categoryManager->getChildrenIdFor($item[$pk]);
//                                }
                            } else {
                                unset($item[$pk]);
//                            $item[$pk] = null;
                            }
                        }
                    }

                    foreach ($mva as $pk => $table) {
                        if (isset($item[$pk])) {
                            if ($item[$pk]) {
                                $item[$pk] = array_map('intval', explode(',', $item[$pk]));
                            } else {
                                unset($item[$pk]);
                            }
                        }
                    }

                    foreach (array_keys($injectAttrIdToName) as $attrIdToInject) {
                        if (isset($item[$attrIdToInject])) {
                            $manager = $managers[$attrIdToInject];
                            $table = $manager->getEntity()->getTable();

                            if (is_array($item[$attrIdToInject])) {
                                $item[$table] = array_map(function ($id) use ($attrIdToInject, $injectAttrIdToName) {
                                    return [
                                        'id' => $id,
                                        'name' => $injectAttrIdToName[$attrIdToInject][$id]
                                    ];
                                }, $item[$attrIdToInject]);
                            } else {
                                $item[$table] = [
                                    'id' => $item[$attrIdToInject],
                                    'name' => $injectAttrIdToName[$attrIdToInject][$item[$attrIdToInject]]
                                ];
                            }

                            unset($item[$attrIdToInject]);
                        }
                    }

                    foreach ($searchColumns as $column) {
                        if (isset($item[$column])) {
                            $item[$column . '_length'] = mb_strlen($item[$column]);
                        }
                    }

                    $id = $item[$itemPk];
                    unset($item[$itemPk]);

//                    $item2 = Arrays::filterByKeysArray($item, $mappingKeys);
//
//                    if ($item !=$item2) {
//                        var_dump($item,$item2);die;
//                    }

                    return [$id, $item];
                });

                $aff += $elastic->indexMany($index, $items);

                return end($items) ? key($items) : false;
            })
            ->run();

        return $aff;
    }

    public function doIndexElastic($where = null): int
    {
//        if ($where) {
//            return $this->doDeleteMissingElastic() + $this->doRawIndexElastic(null, $where);
//        }

        $elastic = $this->app->storage->elastic;
        $alias = $this->app->managers->items->getEntity()->getTable();
        $newIndex = $alias . '_' . time();

        $this->doCreateElasticIndex($newIndex);

        return $elastic->switchAliasIndex($alias, $newIndex, function ($index) {
            return $this->doRawIndexElastic($index);
        });
    }

    /**
     * @todo add missing documents sync support
     * @return int
     */
    public function doDeleteMissingElastic(): int
    {
        return 0;
    }

    public function doDeleteElastic(array $id)
    {
        //@todo
    }
}