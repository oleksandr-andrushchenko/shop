<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Db\DbInterface;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Helper\WalkChunk;
use SNOWGIRL_CORE\Helper\WalkChunk2;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_CORE\Query;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;
use SNOWGIRL_SHOP\Catalog\SRC;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Item as ItemEntity;
use SNOWGIRL_SHOP\Item\FixWhere;
use SNOWGIRL_SHOP\Item\URI as ItemUri;
use SNOWGIRL_SHOP\Entity\Brand;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;
use SNOWGIRL_SHOP\Entity\Item\Archive as ItemArchive;
use SNOWGIRL_SHOP\Manager\Item\IndexerHelper;
use Throwable;

/**
 * Class Item
 * @property App app
 * @package SNOWGIRL_SHOP\Util
 */
class Item extends Util
{
    /**
     * @var IndexerHelper
     */
    private $indexerHelper;
    private $inStockOnly;

    protected function initialize()
    {
        parent::initialize();

        $this->indexerHelper = new IndexerHelper();
        $this->inStockOnly = !!$this->app->configMasterOrOwn('catalog.in_stock_only', false);
    }

    public function doFixSeoNames()
    {
        $db = $this->app->container->db;

        (new WalkChunk(1000))
            ->setFnGet(function ($page, $size) use ($db) {
                return $db->reqToArrays(implode(' ', [
                    'SELECT ' . $db->quote('b') . '.*, COUNT(*) AS ' . $db->quote('cnt'),
                    'FROM ' . $db->quote(Brand::getTable()) . ' AS ' . $db->quote('b'),
                    'INNER JOIN ' . $db->quote(ItemEntity::getTable()) . ' AS ' . $db->quote('i') . ' USING(' . $db->quote(Brand::getPk()) . ')',
                    'GROUP BY ' . $db->quote(Brand::getPk(), 'b'),
                    'LIMIT ' . (($page - 1) * $size) . ', ' . $size,
                ]));
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
                                        'WHERE ' . $db->quote(Brand::getPk()) . ' = ' . $brand->getId(),
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
                                        'WHERE ' . $db->quote(Brand::getPk()) . ' = ' . $brand->getId(),
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
                    } catch (Throwable $e) {
                        $this->app->container->logger->error($e);
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
        $db = $this->app->container->db;
        $after = 'is_in_stock';

        foreach (SRC::getOrderValues() as $order) {
            $uri = new URI([URI::ORDER => $order]);
            $src = $uri->getSRC();

            $info = $src->getOrderInfo();

            if (!in_array($info->cache_column, $db->getManager()->getColumns($table))) {
                $db->req(implode(' ', [
                    'ALTER TABLE' . ' ' . $db->quote($table),
                    'ADD COLUMN ' . $db->quote($info->cache_column) . ' int(11) NOT NULL DEFAULT \'0\'',
                    'AFTER ' . $db->quote($after),
                ]));
            }

            $db->req('SET @num=0');

            $query = new Query(['params' => []]);
            $query->text = implode(' ', [
                'UPDATE ' . $db->quote($table) . ' AS ' . $db->quote('i'),
                'INNER JOIN (',
                'SELECT ' . $db->quote($pk) . ', @num:=@num+1 AS ' . $db->quote('num'),
                'FROM ' . $db->quote($table),
                $db->makeOrderSQL($src->getDataProvider('db')->getOrder(), $query->params),
                ') AS ' . $db->quote('i2') . ' USING(' . $db->quote($pk) . ')',
                'SET ' . $db->quote($info->cache_column, 'i') . ' = ' . $db->quote('num', 'i2'),
            ]);

            $aff = $db->req($query)->affectedRows();

            $after = $info->cache_column;

            $this->output('DONE[' . $info->cache_column . ']: ' . $aff);
        }

        $this->output('DONE');

        return true;
    }

    public function doDeleteWithNonExistingCategories(FixWhere $fixWhere = null, array $params = [])
    {
        $db = $this->app->container->db;
        $it = $this->app->managers->items->getEntity()->getTable();
        $ck = $this->app->managers->categories->getEntity()->getPk();
        $ct = $this->app->managers->categories->getEntity()->getTable();

        $where = $fixWhere ? $fixWhere->get() : [];
        $where[] = new Expression($db->quote($ck, $ct) . ' IS NULL');

        $query = new Query($params);
        $query->params = [];
        $query->text = implode(' ', [
            'DELETE ' . $db->quote($it),
            'FROM ' . $db->quote($it),
            'LEFT JOIN ' . $db->quote($ct) . ' ON ' . $db->quote($ck, $ct) . ' = ' . $db->quote($ck, $it),
            $db->makeWhereSQL($where, $query->params),
        ]);

        return $this->app->container->db->req($query)->affectedRows();
    }

    public function doDeleteWithNonExistingBrands(FixWhere $fixWhere = null, array $params = [])
    {
        $db = $this->app->container->db;
        $it = $this->app->managers->items->getEntity()->getTable();
        $bk = $this->app->managers->brands->getEntity()->getPk();
        $bt = $this->app->managers->brands->getEntity()->getTable();

        $where = $fixWhere ? $fixWhere->get() : [];
        $where[] = new Expression($db->quote($bk, $bt) . ' IS NULL');

        $query = new Query($params);
        $query->params = [];
        $query->text = implode(' ', [
            'DELETE ' . $db->quote($it),
            'FROM ' . $db->quote($it),
            'LEFT JOIN ' . $db->quote($bt) . ' ON ' . $db->quote($bk, $bt) . ' = ' . $db->quote($bk, $it),
            $db->makeWhereSQL($where, $query->params),
        ]);

        return $this->app->container->db->req($query)->affectedRows();
    }

    public function doFixWithNonExistingCountries(FixWhere $fixWhere = null, array $params = [])
    {
        $db = $this->app->container->db;
        $pk = $this->app->managers->countries->getEntity()->getPk();

        $id = $this->app->managers->countries->getList($pk);

        $where = $fixWhere ? $fixWhere->get() : [];
        $where[] = new Expression($db->quote($pk) . ' NOT IN (' . implode(',', $id) . ')');

        return $this->app->managers->items->updateMany([$pk => null], $where, $params);
    }

    public function doFixWithNonExistingAttrs(FixWhere $fixWhere = null, array $params = [])
    {
        $aff = $this->doDeleteWithNonExistingCategories($fixWhere, $params);
        $this->output('deleted with invalid categories: ' . $aff);

        $tmp = $this->doDeleteWithNonExistingBrands($fixWhere, $params);
        $this->output('deleted with invalid brands: ' . $tmp);
        $aff += $tmp;

        $tmp = $this->doFixWithNonExistingCountries($fixWhere, $params);
        $this->output('updated with invalid countries: ' . $tmp);
        $aff += $tmp;

        return $aff;
    }

    protected $archiveIgnore = [
        'rating',
        'is_active',

//        'order_desc_relevance',
//        'order_desc_rating',
//        'order_asc_price',
//        'order_desc_price'
    ];

    public function doCreateArchiveTable()
    {
        $db = $this->app->container->db;

        $itemTable = $this->app->managers->items->getEntity()->getTable();
        $showCreate = $db->getManager()->showCreateTable($itemTable, true);

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

        $db = $this->app->container->db;

        $itemTable = $this->app->managers->items->getEntity()->getTable();
        $itemPk = $this->app->managers->items->getEntity()->getPk();

        $archiveTable = $this->app->managers->archiveItems->getEntity()->getTable();
        $archiveColumns = $db->getManager()->getColumns($archiveTable);

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
                $db->makeWhereSQL($where, $query->params, $itemTable),
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

        $db = $this->app->container->db;

        $itemTable = $this->app->managers->items->getEntity()->getTable();
        $itemPk = $this->app->managers->items->getEntity()->getPk();

        $archiveTable = $this->app->managers->archiveItems->getEntity()->getTable();
        $archiveColumns = $db->getManager()->getColumns($archiveTable);

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
            ')',
        ]);

        $affTmp = $db->req($query)->affectedRows();
        $this->output('Copied to item: ' . $affTmp);

        $aff += $affTmp;

        $db->getManager()->dropTable('numbers');
        $db->getManager()->createTable('numbers', [
            ($qv = $db->quote('value')) . ' TINYINT(1) UNSIGNED NOT NULL',
            'PRIMARY KEY (' . $qv . ')',
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
                'ORDER BY ' . $db->quote($itemPk) . ', ' . $db->quote($pk),
            ]))->affectedRows();
            $this->output('Copied to item_' . $table . ': ' . $affTmp);

            $aff += $affTmp;
        }

        $affTmp = $this->app->managers->archiveItems->deleteMany($where);
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

        $mysql = $this->app->container->db;

        (new WalkChunk2(1000))
            ->setFnGet(function ($lastId, $size) use ($mysql, $itemPk) {
                $where = [];

                if ($lastId) {
                    $where[] = new Expression($mysql->quote($itemPk) . ' > ?', $lastId);
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
        $db = $this->app->container->db;
        $table = $this->app->managers->items->getEntity()->getTable();
        $pk = $this->app->managers->items->getEntity()->getPk();

        (new WalkChunk(1000))
            ->setFnGet(function ($page, $size) use ($importSourceId, $db, $table, $pk) {
                return $db->reqToArrays(implode(' ', [
                    'SELECT ' . implode(', ', [
                        $db->quote('image'),
                        'GROUP_CONCAT(' . $db->quote($pk) . ') AS ' . $db->quote($pk),
                        'GROUP_CONCAT(' . $db->quote('name') . ') AS ' . $db->quote('name'),
                        'COUNT(*) AS ' . $db->quote('cnt'),
                    ]),
                    'FROM ' . $db->quote($table),
                    'WHERE ' . $db->quote('import_source_id') . ' = ' . $importSourceId,
                    'GROUP BY ' . $db->quote('image'),
                    'HAVING ' . $db->quote('cnt') . ' > 1',
//                    'LIMIT ' . (($page - 1) * $size) . ', ' . $size
                    'LIMIT ' . $size,
                ]));
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
                            'id_to' => $insert,
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
            $where[] = new Expression($this->app->container->db->quote('category_id') . ' NOT IN (' . implode(', ', $categoryIds) . ')');

            $aff = $this->app->managers->items->deleteMany($where);

            if ($isOk = $aff > 0) {
                $this->app->container->logger->debug('There are were ' . $aff . 'items with invalid categories... Deleted...');
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
     * @todo already created:
     * [category_id,    vendor_id, created_at, updated_at]
     * [                vendor_id, created_at, updated_at]
     * @todo to run this without vendor_id or time intervals - need to create indexes (!)
     * @param FixWhere|null $fixWhere
     * @param array $params
     * @return null|int
     */
    public function doFixItemsCategories(FixWhere $fixWhere = null, array $params = []): ?int
    {
        $aff = 0;

        $manager = $this->app->managers->categoriesToEntities;

        $manager->generate();

//        $time = time();

        //the highest priority
        $aff += $manager->updateByParentsAndEntities($fixWhere, $params);

        if (!$fixWhere) {
            $fixWhere = new FixWhere($this->app);
        }

        // do not touch those which were changed on previous call
//        $fixWhere->setUpdatedAtTo($time, true);
        $fixWhere->setUpdatedAtIsNull(true);

//        $manager->updateByParentsAndNamesLikeCategories($fixWhere, $params);

//        $manager->updateByEntities($fixWhere, $params);

//        $manager->updateByEntitiesLikeEntities($fixWhere, $params);
//        $manager->updateByNamesLikeEntities($fixWhere, $params);

        $aff += $manager->updateByParentsAndNamesLikeEntities($fixWhere, $params);

        //update for admin
        $manager->generate(true);

        return $aff;
    }

    public function doTransferByAttrs($source, $target)
    {
        return $this->app->container->db->makeTransaction(function (DbInterface $db) use ($target, $source) {
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
                        ItemEntity::getPk() => $items,
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
                            $attrPk => $attrId,
                        ]);

                        $affGlobal += $aff;

                        $this->output('Affected ' . $linkAttrEntity::getTable() . ' table delete: ' . $aff);
                    }

                    //..insert new ones
                    foreach ($targetMva as $attrPk => $attrId) {
                        /** @var Entity $linkAttrEntity */
                        $linkAttrEntity = ItemAttrManager::makeLinkEntityClassByAttrEntityClass($mva[$attrPk]);

                        foreach (is_array($attrId) ? $attrId : [$attrId] as $attrId2) {
                            $attrId2 = (int) $attrId2;

                            $aff = $this->app->managers->getByEntityClass($linkAttrEntity)->insertMany(array_map(function ($item) use ($attrPk, $attrId2) {
                                return [
                                    'item_id' => $item,
                                    $attrPk => $attrId2,
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
            $aff = $this->app->managers->catalog->updateMany($target, $source, ['ignore' => true]);

            $affGlobal += $aff;

            $this->output('Affected ' . $this->app->managers->catalog->getEntity()->getTable() . ' table insert: ' . $aff);

            return $affGlobal;
        });
    }

    public function doIndexIndexer(int $reindexDays = 0)
    {
        return $this->doIndexElastic(new Expression(implode(' OR ', [
            $this->app->container->db->quote('created_at') . ' >= (CURDATE() - INTERVAL ? DAY)',
            $this->app->container->db->quote('updated_at') . ' >= (CURDATE() - INTERVAL ? DAY)',
        ]), $reindexDays, $reindexDays));
    }

    protected function getSearchColumns(): array
    {
        return $this->app->managers->items->findColumns(Entity::SEARCH_IN);
    }

    protected function getElasticMappings(): array
    {
        $properties = [
            'is_sport' => 'boolean',
            'is_size_plus' => 'boolean',
            'is_sales' => 'boolean',
            'price' => 'integer',
            'rating' => 'integer',

//            'order_desc_relevance' => 'integer',
//            'order_desc_rating' => 'integer',
//            'order_asc_price' => 'integer',
//            'order_desc_price' => 'integer'
        ];

        if (!$this->inStockOnly) {
            $properties['is_in_stock'] = 'boolean';
        }

        foreach ($this->getSearchColumns() as $column) {
            $properties[$column] = 'text';
            $properties[$column . '_length'] = 'short';
        }

        foreach ($properties as $column => &$type) {
            $type = ['type' => $type];
        }

        $sva = $this->app->managers->catalog->getSvaPkToTable();
        $mva = $this->app->managers->catalog->getMvaPkToTable();

        foreach ($this->indexerHelper->getAjaxSuggestionsAttrPkToTable($this->app) as $pk => $table) {
            if (isset($sva[$pk])) {
                $properties[$table] = ['type' => 'object'];
                unset($sva[$pk]);
            } elseif (isset($mva[$pk])) {
                $properties[$table] = ['type' => 'nested'];
                unset($mva[$pk]);
            }

            $properties[$table]['properties'] = [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'text'],
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

    public function doRawIndexElastic(string $index, $where = null): int
    {
        $aff = 0;

        $this->indexerHelper->prepareData($this->app);

        $where = Arrays::cast($where);

        if ($this->inStockOnly) {
            $where['is_in_stock'] = 1;
        }

        (new WalkChunk(1000))
            ->setFnGet(function ($page, $size) use ($where) {
                $itemPk = $this->app->managers->items->getEntity()->getPk();
                $itemTable = $this->app->managers->items->getEntity()->getTable();
                $mva = $this->indexerHelper->getMva();
                $mysql = $this->app->container->db;

                $query = new Query(['params' => []]);

                $order = [];

                if (!$this->inStockOnly) {
                    $order['is_in_stock'] = SORT_DESC;
                }

                $order = array_merge($order, [
                    'partner_updated_at' => SORT_DESC,
                    'created_at' => SORT_DESC,
                    'updated_at' => SORT_DESC,
                ]);

                $query->text = implode(' ', [
                    'SELECT ' . implode(', ', array_map(function ($column) use ($mysql, $mva, $itemTable) {
                        if (array_key_exists($column, $mva)) {
                            return 'GROUP_CONCAT(DISTINCT ' . $mysql->quote($column, 'item_' . $mva[$column]) . ') AS ' . $mysql->quote($column);
                        }

                        return $mysql->quote($column, $itemTable);
                    }, $this->indexerHelper->getColumns())),
                    'FROM ' . $mysql->quote($itemTable) . ' ' . implode(' ', array_map(function ($attrTable)
                    use ($mysql, $itemTable, $itemPk) {
                        $linkTable = 'item_' . $attrTable;
                        return 'LEFT JOIN ' . $mysql->quote($linkTable) . ' ON' . $mysql->quote($itemPk, $itemTable) . ' = ' . $mysql->quote($itemPk, $linkTable);
                    }, $mva)),
                    $where ? $mysql->makeWhereSQL($where, $query->params, $itemTable) : '',
//                    $mysql->makeWhereSQL(['item_id' => 309018], $query->params, $itemTable),
                    $mysql->makeGroupSQL($itemPk, $query->params, $itemTable),
                    $mysql->makeOrderSQL($order, $query->params, $itemTable),
                    $mysql->makeLimitSQL(($page - 1) * $size, $size, $query->params),
                ]);

                return $mysql->reqToArrays($query);
            })
            ->setFnDo(function ($items) use ($index, &$aff) {
                $itemPk = $this->app->managers->items->getEntity()->getPk();

                $documents = [];

                foreach ($items as $item) {
                    $documents[$item[$itemPk]] = $this->indexerHelper->getDocumentByArray($item);
                }

                $aff += $this->app->container->indexer->getManager()->indexMany($index, $documents);

                return end($documents) ? key($documents) : false;
            })
            ->run();

        return $aff;
    }

    public function doIndexElastic(): int
    {
        $manager = $this->app->container->indexer->getManager();
        $alias = $this->app->managers->items->getEntity()->getTable();
        $mappings = $this->getElasticMappings();

        return $manager->switchAliasIndex($alias, $mappings, function ($newIndex) {
            return $this->doRawIndexElastic($newIndex);
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