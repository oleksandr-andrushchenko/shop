<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Helper\WalkChunk;
use SNOWGIRL_CORE\Helper\WalkChunk2;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Mysql\Mysql;
use SNOWGIRL_CORE\Mysql\MysqlQueryExpression;
use SNOWGIRL_CORE\Mysql\MysqlQuery;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;
use SNOWGIRL_SHOP\Catalog\SRC;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Item as ItemEntity;
use SNOWGIRL_SHOP\Item\FixWhere;
use SNOWGIRL_SHOP\Item\URI as ItemUri;
use SNOWGIRL_SHOP\Entity\Brand;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;
use SNOWGIRL_SHOP\Manager\Item\IndexerHelper;
use Throwable;

/**
 * @property App app
 */
class Item extends Util
{
    /**
     * @var IndexerHelper
     */
    private $indexerHelper;

    protected function initialize()
    {
        parent::initialize();

        $this->indexerHelper = new IndexerHelper();
    }

    public function doFixSeoNames()
    {
        $mysql = $this->app->container->mysql;

        (new WalkChunk(1000))
            ->setFnGet(function ($page, $size) use ($mysql) {
                return $mysql->reqToArrays(implode(' ', [
                    'SELECT ' . $mysql->quote('b') . '.*, COUNT(*) AS ' . $mysql->quote('cnt'),
                    'FROM ' . $mysql->quote(Brand::getTable()) . ' AS ' . $mysql->quote('b'),
                    'INNER JOIN ' . $mysql->quote(ItemEntity::getTable()) . ' AS ' . $mysql->quote('i') . ' USING(' . $mysql->quote(Brand::getPk()) . ')',
                    'GROUP BY ' . $mysql->quote(Brand::getPk(), 'b'),
                    'LIMIT ' . (($page - 1) * $size) . ', ' . $size,
                ]));
            })
            ->setFnDo(function ($items) use ($mysql) {
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
                                    $this->output('...trying to update ' . $mysql->quote(Brand::getPk()) . ' that relates to our new ' . $mysql->quote('uri') . '...');

                                    $aff = $mysql->req(implode(' ', [
                                        'UPDATE ' . $mysql->quote(ItemEntity::getTable()),
                                        'SET ' . $mysql->quote(Brand::getPk()) . ' =',
                                        '(SELECT ' . $mysql->quote(Brand::getPk()),
                                        'FROM ' . $mysql->quote(Brand::getTable()),
                                        'WHERE ' . $mysql->quote('uri') . ' = \'' . $brand->getUri() . '\')',
                                        'WHERE ' . $mysql->quote(Brand::getPk()) . ' = ' . $brand->getId(),
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
                                    $this->output('...trying to update ' . $mysql->quote(Brand::getPk()) . ' that relates to our new ' . $mysql->quote('name') . '...');

                                    $aff = $mysql->req(implode(' ', [
                                        'UPDATE ' . $mysql->quote(ItemEntity::getTable()),
                                        'SET ' . $mysql->quote(Brand::getPk()) . ' =',
                                        '(SELECT ' . $mysql->quote(Brand::getPk()),
                                        'FROM ' . $mysql->quote(Brand::getTable()),
                                        'WHERE ' . $mysql->quote('name') . ' = \'' . $brand->getName() . '\')',
                                        'WHERE ' . $mysql->quote(Brand::getPk()) . ' = ' . $brand->getId(),
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

    public function doUpdateItemsOrders(): int
    {
        $aff = 0;

        foreach (SRC::getOrderValues() as $order) {
            $uri = new URI([
                URI::ORDER => $order,
            ]);

            $src = $uri->getSRC();

            $info = $src->getOrderInfo();

            $affTmp = $this->addOrderColumn($src->getDataProvider('mysql')->getOrder(), $info->cache_column);

            $aff += $affTmp;

            $this->output("column {$info->cache_column} has been created, updated for {$affTmp} items");
        }

        return $aff;
    }

    public function doDeleteWithNonExistingCategories(FixWhere $fixWhere = null, array $params = [])
    {
        $mysql = $this->app->container->mysql;
        $it = $this->app->managers->items->getEntity()->getTable();
        $ck = $this->app->managers->categories->getEntity()->getPk();
        $ct = $this->app->managers->categories->getEntity()->getTable();

        $where = $fixWhere ? $fixWhere->get() : [];
        $where[] = new MysqlQueryExpression($mysql->quote($ck, $ct) . ' IS NULL');

        $query = new MysqlQuery($params);
        $query->params = [];
        $query->text = implode(' ', [
            'DELETE ' . $mysql->quote($it),
            'FROM ' . $mysql->quote($it),
            'LEFT JOIN ' . $mysql->quote($ct) . ' ON ' . $mysql->quote($ck, $ct) . ' = ' . $mysql->quote($ck, $it),
            $mysql->makeWhereSQL($where, $query->params, null, $query->placeholders),
        ]);

        return $this->app->container->mysql->req($query)->affectedRows();
    }

    public function doDeleteWithNonExistingBrands(FixWhere $fixWhere = null, array $params = [])
    {
        $mysql = $this->app->container->mysql;
        $it = $this->app->managers->items->getEntity()->getTable();
        $bk = $this->app->managers->brands->getEntity()->getPk();
        $bt = $this->app->managers->brands->getEntity()->getTable();

        $where = $fixWhere ? $fixWhere->get() : [];
        $where[] = new MysqlQueryExpression($mysql->quote($bk, $bt) . ' IS NULL');

        $query = new MysqlQuery($params);
        $query->params = [];
        $query->text = implode(' ', [
            'DELETE ' . $mysql->quote($it),
            'FROM ' . $mysql->quote($it),
            'LEFT JOIN ' . $mysql->quote($bt) . ' ON ' . $mysql->quote($bk, $bt) . ' = ' . $mysql->quote($bk, $it),
            $mysql->makeWhereSQL($where, $query->params, null, $query->placeholders),
        ]);

        return $this->app->container->mysql->req($query)->affectedRows();
    }

    public function doFixWithNonExistingCountries(FixWhere $fixWhere = null, array $params = [])
    {
        $mysql = $this->app->container->mysql;
        $pk = $this->app->managers->countries->getEntity()->getPk();

        $id = $this->app->managers->countries->getList($pk);

        $where = $fixWhere ? $fixWhere->get() : [];
        $where[] = new MysqlQueryExpression($mysql->quote($pk) . ' NOT IN (' . implode(',', $id) . ')');

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

    public function doFixDuplicates($importSourceId)
    {
        $mysql = $this->app->container->mysql;
        $table = $this->app->managers->items->getEntity()->getTable();
        $pk = $this->app->managers->items->getEntity()->getPk();

        (new WalkChunk(1000))
            ->setFnGet(function ($page, $size) use ($importSourceId, $mysql, $table, $pk) {
                return $mysql->reqToArrays(implode(' ', [
                    'SELECT ' . implode(', ', [
                        $mysql->quote('image'),
                        'GROUP_CONCAT(' . $mysql->quote($pk) . ') AS ' . $mysql->quote($pk),
                        'GROUP_CONCAT(' . $mysql->quote('name') . ') AS ' . $mysql->quote('name'),
                        'COUNT(*) AS ' . $mysql->quote('cnt'),
                    ]),
                    'FROM ' . $mysql->quote($table),
                    'WHERE ' . $mysql->quote('import_source_id') . ' = ' . $importSourceId,
                    'GROUP BY ' . $mysql->quote('image'),
                    'HAVING ' . $mysql->quote('cnt') . ' > 1',
//                    'LIMIT ' . (($page - 1) * $size) . ', ' . $size
                    'LIMIT ' . $size,
                ]));
            })
            ->setFnDo(function ($rows) use ($mysql, $pk) {
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
            $where[] = new MysqlQueryExpression($this->app->container->mysql->quote('category_id') . ' NOT IN (' . implode(', ', $categoryIds) . ')');

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
        return $this->app->container->mysql->makeTransaction(function (Mysql $mysql) use ($target, $source) {
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
                ->setFnGet(function ($page, $size) use ($source, $mysql) {
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

    public function doIndexIndexer()
    {
        return $this->doIndexElastic();
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

    public function doRawIndexElastic(string $index): int
    {
        $aff = 0;

        $this->indexerHelper->prepareData($this->app);

        $where = [
            'is_in_stock' => 1,
        ];
        $order = [];

        $order = array_merge($order, [
            'partner_updated_at' => SORT_DESC,
            'created_at' => SORT_DESC,
            'updated_at' => SORT_DESC,
        ]);

        // @todo speed up
        $orderColumn = 'tmp_index_elastic';

        $this->addOrderColumn($order, $orderColumn, array_merge(array_keys($where), [$orderColumn]));

        $itemTable = $this->app->managers->items->getEntity()->getTable();
        $itemPk = $this->app->managers->items->getEntity()->getPk();

        (new WalkChunk2(1000))
            ->setFnGet(function ($lastId, $size) use ($itemPk, $where, $orderColumn) {
                $mysql = $this->app->container->mysql;

                if ($lastId) {
                    $where[] = new MysqlQueryExpression($mysql->quote($orderColumn) . ' > ?', $lastId);
                }

                return $this->app->managers->items->clear()
                    ->setColumns($this->indexerHelper->getColumns(true))
                    ->addColumn($orderColumn)
                    ->setWhere($where)
                    ->setOrders([$orderColumn => SORT_ASC])
                    ->setOffset(0)
                    ->setLimit($size)
                    ->getArrays($itemPk);
            })
            ->setFnDo(function ($items) use ($index, $itemPk, $orderColumn, &$aff) {
                $mysql = $this->app->container->mysql;
                $where = [$itemPk => array_map(function ($item) use ($itemPk) {
                    return $item[$itemPk];
                }, $items)];

                // @todo test...
                foreach ($this->indexerHelper->getMva() as $mvaColumn => $mvaTable) {
                    $query = new MysqlQuery();
                    $query->params = [];
                    $query->text = implode(' ', [
                        'SELECT *',
                        'FROM ' . $mysql->quote('item_' . $mvaTable),
                        $mysql->makeWhereSQL($where, $query->params, null, $query->placeholders),
                    ]);

                    foreach ($mysql->reqToArrays($query) as $row) {
                        if (!isset($items[$row[$itemPk]][$mvaColumn])) {
                            $items[$row[$itemPk]][$mvaColumn] = [];
                        }

                        $items[$row[$itemPk]][$mvaColumn][] = $row[$mvaColumn];
                    }
                }

                $documents = [];

                foreach ($items as $item) {
                    $documents[$item[$itemPk]] = $this->indexerHelper->getDocumentByArray($item);
                }

                $aff += $this->app->container->elasticsearch->indexMany($index, $documents);

                return end($documents) ? $documents[key($documents)][$orderColumn] : false;
            })
            ->run();

        $this->app->container->mysql->dropTableColumn($itemTable, $orderColumn);

        return $aff;
    }

    public function doRawReIndexElastic(string $index, $where = null): int
    {
        // @todo...
    }

    public function doIndexElastic(): int
    {
        $alias = $this->app->managers->items->getEntity()->getTable();

        $mappings = $this->getElasticMappings();

        return $this->app->container->elasticsearch->switchAliasIndex($alias, $mappings, function ($newIndex) {
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

    private function addOrderColumn(array $order, string $column, array $index = []): int
    {
        $table = $this->app->managers->items->getEntity()->getTable();
        $pk = $this->app->managers->items->getEntity()->getPk();
        $mysql = $this->app->container->mysql;

        if (!$mysql->columnExists($table, $column)) {
            $mysql->addTableColumn($table, $column, 'int(11) NOT NULL DEFAULT \'0\'');
        }

        $mysql->req('SET @num=0');

        $query = new MysqlQuery(['params' => []]);
        $query->text = implode(' ', [
            'UPDATE ' . $mysql->quote($table) . ' AS ' . $mysql->quote('i'),
            'INNER JOIN (',
            'SELECT ' . $mysql->quote($pk) . ', @num:=@num+1 AS ' . $mysql->quote('num'),
            'FROM ' . $mysql->quote($table),
            $mysql->makeOrderSQL($order, $query->params),
            ') AS ' . $mysql->quote('i2') . ' USING(' . $mysql->quote($pk) . ')',
            'SET ' . $mysql->quote($column, 'i') . ' = ' . $mysql->quote('num', 'i2'),
        ]);

        $aff = $mysql->req($query)->affectedRows();

        if ($index) {
            if ($mysql->indexExists($table, $index)) {
                $this->app->container->logger->warning('index for (' . implode(',', $index) . ') is already exists');
            } else {
                $mysql->addTableKey($table, $index);
            }
        }

        return $aff;
    }
}