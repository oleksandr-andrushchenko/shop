<?php

namespace SNOWGIRL_SHOP\Util;

use Exception;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Helper\Classes;
use SNOWGIRL_CORE\Helper\WalkChunk2;
use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_CORE\Util;

use SNOWGIRL_SHOP\Catalog\SEO;
use SNOWGIRL_SHOP\Catalog\SRC;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Entity\Category\Child as CategoryChild;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttr;
use SNOWGIRL_SHOP\Entity\Item\Attr\Alias as ItemAttrAlias;
use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalog;
use SNOWGIRL_SHOP\Entity\Page\Catalog\Custom as PageCatalogCustom;

use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Size;
use SNOWGIRL_SHOP\Entity\Tag;
use SNOWGIRL_SHOP\Http\HttpApp;
use SNOWGIRL_SHOP\Manager\Page\Catalog\IndexerHelper;
use Throwable;

/**
 * Class Catalog
 * @property HttpApp|ConsoleApp app
 * @package SNOWGIRL_SHOP\Util
 */
class Catalog extends Util
{
    /**
     * @var IndexerHelper
     */
    private $indexerHelper;

    private $components;
    private $mvaComponents;
    private $types;
    private $itemTable;
    private $catalogTable;

    public function doMigrateCatalogToCustom()
    {
        $tableFrom = PageCatalog::getTable();
        $tableTo = PageCatalogCustom::getTable();
        $columns = [
            'uri_hash',
            'meta_title',
            'meta_description',
            'meta_keywords',
            'h1',
            'body',
            'seo_texts',
            'updated_at' => 'created_at',
        ];
        $where = new Expression($this->app->container->db->quote('updated_at') . ' IS NOT NULL');

        return $this->app->utils->database->doMigrateDataFromTableToTable($tableFrom, $tableTo, $columns, $where);
    }

    public function doIndexIndexer()
    {
        return $this->doIndexElastic();
    }

    public function doRawIndexElastic(string $index, $where = null): int
    {
        $aff = 0;

        $this->indexerHelper->prepareData($this->app);

        $manager = $this->app->managers->catalog->copy(true)
            ->setColumns($this->indexerHelper->getColumns())
            ->setOrders([$this->app->managers->catalog->getEntity()->getPk() => SORT_ASC]);

        $where = Arrays::cast($where);

        (new WalkChunk2(1000))
            ->setFnGet(function ($lastId, $size) use ($manager, $where) {
                if ($lastId) {
                    $itemPk = $this->app->managers->catalog->getEntity()->getPk();
                    $where[] = new Expression($this->app->container->db->quote($itemPk) . ' > ?', $lastId);
                }

                return $manager
                    ->setWhere($where)
                    ->setLimit($size)
                    ->getArrays();
            })
            ->setFnDo(function ($items) use ($index, &$aff) {
                $itemPk = $this->app->managers->catalog->getEntity()->getPk();

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
        $alias = $this->app->managers->catalog->getEntity()->getTable();
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

    public function doGenerate(): int
    {
        $this->components = $this->app->managers->catalog->getComponentsOrderByDbKey();
        $this->mvaComponents = $this->app->managers->catalog->getMvaComponents();
        $this->types = URI::TYPE_PARAMS;
        $this->itemTable = $this->app->managers->items->getEntity()->getTable();
        $this->catalogTable = $this->app->managers->catalog->getEntity()->getTable();
        $andAliases = !!$this->app->config('catalog.aliases', false);

        $aff = 0;

        $this->app->container->db->getManager()->truncateTable($this->catalogTable);

        $aff += $this->generateCatalogPages(false);

        if ($andAliases) {
            $aff += $this->generateCatalogPages(true);
        }

        return $aff;
    }

    protected function initialize()
    {
        parent::initialize();

        $this->indexerHelper = new IndexerHelper();
    }

    private function getElasticMappings(): array
    {
        $this->indexerHelper->prepareData($this->app);

        $properties = [
            'uri' => 'text',
            'count' => 'integer',
        ];

        foreach ($this->indexerHelper->getSearchColumns() as $column) {
            $properties[$column] = 'text';
            $properties[$column . '_length'] = 'short';
        }

        foreach ($properties as $column => &$type) {
            $type = ['type' => $type];
        }

        return ['properties' => $properties];
    }

    /**
     * @param bool $aliases
     * @return int|null
     */
    private function generateCatalogPages(bool $aliases = false): ?int
    {
        $aff = 0;

        $this->output(__FUNCTION__ . '...');

        $this->app->managers->categories->syncTree();

        $db = $this->app->container->db;

        $components = $this->components;
        $types = $this->types;

        $mvaComponents = $this->mvaComponents;

        if ($aliases) {
            $components = $this->replaceWithAliases($components);

            $tmp = array_filter($components, function ($component) {
                return $this->isComponentAlias($component);
            });

            if (0 == count($tmp)) {
                return null;
            }

            $mvaComponents = $this->replaceWithAliases($mvaComponents);
        }

        $typesColumns = SRC::getTypesToColumns();
        $typesTexts = SEO::getTypesToTexts();

        $componentsAndTypes = array_merge($components, $types);

        $aliasPostfix = '\\Alias';

        $combinations = array_filter(Arrays::getUniqueCombinations($componentsAndTypes), function ($combination) use ($aliasPostfix, $aliases, $types) {
            if (in_array(Size::class, $combination) || in_array(Size::class . $aliasPostfix, $combination)) {
                return false;
            }

            //if non-type and no have category
            if (0 == count(array_intersect($combination, $types))) {
                if (!in_array(Category::class, $combination) && !in_array(Category::class . $aliasPostfix, $combination)) {
                    return false;
                }
            }

            //if tag and no category
            if (in_array(Tag::class, $combination) || in_array(Tag::class . $aliasPostfix, $combination)) {
                if (!in_array(Category::class, $combination) && !in_array(Category::class . $aliasPostfix, $combination)) {
                    return false;
                }
            }

            //place new conditions here...

            if ($aliases) {
                //remove this when types have aliases too
                if (0 == count(array_diff($combination, $types))) {
                    return false;
                }

                foreach ($combination as $componentOrComponentAliasOrType) {
                    if ($this->isComponentAlias($componentOrComponentAliasOrType)) {
                        return true;
                    }
                }

                return false;
            }

            return true;
        });

        $combinations = array_values($combinations);

        $columnsToInsert = [
            'name',
            'uri',
//            'uri_hash',
            'params',
//            'params_hash',
//            'meta',
        ];

        $add = [
            'uri_hash' => 'MD5(' . $db->quote('uri') . ')',
            'params_hash' => 'MD5(' . $db->quote('params') . ')',
            'meta' => 'CONCAT(\'{"count":\', ' . $db->quote('cnt') . ', ' . $db->quote('meta_add') . ', \'}\')',
        ];

        $queryTmp = implode(', ', array_map(function ($column) use ($db) {
            return $db->quote($column);
        }, $columnsToInsert));

        $queryColumnsToInsert = $queryTmp . ', ' . implode(', ', array_map(function ($column) use ($db) {
                return $db->quote($column);
            }, array_keys($add)));

        $queryColumnsToSelect = $queryTmp . ', ' . implode(', ', $add);

//        $combinations = [
//            [Category::class . $classPostfix, Brand::class . $classPostfix, URI::SALES],
//            [Category::class . $classPostfix, Brand::class . $classPostfix],
//            [URI::SALES, URI::SIZE_PLUS]
//        ];

        $s = count($combinations);

        $j = 1;

        foreach ($combinations as $combination) {
            $this->output('#' . $j . ' ouf of ' . $s . '...');
            $j++;

            $componentsAndTypesToSelect = array_values(array_filter($componentsAndTypes, function ($componentOrType) use ($combination) {
                return in_array($componentOrType, $combination);
            }));

            $componentsToSelect = array_values(array_filter($componentsAndTypesToSelect, function ($componentOrType) use ($components) {
                return in_array($componentOrType, $components);
            }));

            $checkCount = 0 == count($componentsToSelect);

            $typesToSelect = array_filter($componentsAndTypesToSelect, function ($componentOrType) use ($types) {
                return in_array($componentOrType, $types);
            });

            $queryParamsColumn = [];

            foreach ($componentsAndTypesToSelect as $componentOrType) {
                if (in_array($componentOrType, $components)) {
                    /** @var $componentOrType ItemAttr|ItemAttrAlias */
                    if ($aliases && $this->isComponentAlias($componentOrType)) {
                        $attrPk = $componentOrType::getAttrPk();
                        $attrTable = $componentOrType::getAttrTable();
                    } else {
                        $attrPk = $componentOrType::getPk();
                        $attrTable = $componentOrType::getTable();
                    }

                    $tmp = '\'"' . $attrPk . '":\', ';

                    if (in_array($componentOrType, $mvaComponents)) {
                        $tmp .= $db->quote($attrPk, 'item_' . $attrTable);
                    } elseif (Category::getTable() == $attrTable) {
                        $tmp .= $db->quote($attrPk, CategoryChild::getTable());
                    } else {
                        $tmp .= $db->quote($attrPk, $componentOrType::getTable());
                    }

                    $queryParamsColumn[] = $tmp;
                } elseif (in_array($componentOrType, $types)) {
                    $queryParamsColumn[] = '\'"' . $componentOrType . '":1\'';
                } else {
                    new Exception('undefined component or type[' . $componentOrType . ']');
                }
            }

            $mapName = function ($componentOrType) use ($combination, $db, $components, $types, $typesTexts) {
                if (in_array($componentOrType, $components)) {
                    /** @var $componentOrType ItemAttr|ItemAttrAlias */
                    $table = $componentOrType::getTable();

                    if (array_key_exists('name_multiply', $componentOrType::getColumns())) {
                        $tmp = $db->quote('name_multiply', $table);
                        return 'IF(' . $tmp . ' IS NULL OR ' . $tmp . ' = \'\', ' . $db->quote('name', $table) . ', ' . $tmp . ')';
                    }

                    return $db->quote('name', $table);
                } elseif (in_array($componentOrType, $types)) {
                    return '\'' . $typesTexts[$componentOrType] . '\'';
                } else {
                    throw new Exception('undefined component or type[' . $componentOrType . ']');
                }
            };

            //do not change  this logic, coz of page_catalog_custom.uri_hash link
            $mapUri = function ($componentOrType) use ($combination, $db, $components, $types) {
                if (in_array($componentOrType, $components)) {
                    /** @var $componentOrType ItemAttr|ItemAttrAlias */
                    return $db->quote('uri', $componentOrType::getTable());
                } elseif (in_array($componentOrType, $types)) {
                    return '\'' . $componentOrType . '\'';
                } else {
                    throw new Exception('undefined component or type[' . $componentOrType . ']');
                }
            };

            $expr = [
                'name' => ((1 < count($componentsAndTypesToSelect))
                    ? ('CONCAT(' . implode(', \' \', ', array_map($mapName, $componentsAndTypesToSelect)) . ')')
                    : $mapName($componentsAndTypesToSelect[0])),
                'uri' => (1 < count($componentsAndTypesToSelect)
                    ? ('CONCAT(' . implode(', \'/\', ', array_map($mapUri, $this->orderAccordingPagePath($componentsAndTypesToSelect, $aliases))) . ')')
                    : $mapUri($componentsAndTypesToSelect[0])),
                'params' => 'CONCAT(\'{\', ' . implode(', \',\', ', $queryParamsColumn) . ', \'}\')',
            ];

            $baseColumnsToSelect = [];

            foreach ($columnsToInsert as $column) {
                if (isset($expr[$column])) {
                    $baseColumnsToSelect[] = $expr[$column] . ' AS ' . $db->quote($column);
                } else {
                    new Exception('undefined column expr[' . $column . ']');
                }
            }

            $baseColumnsToSelect[] = 'COUNT(*) AS ' . $db->quote('cnt');

            if ($aliases) {
                $queryAliasesColumn = [];

                foreach ($componentsAndTypesToSelect as $componentOrType) {
                    if (in_array($componentOrType, $components)) {
                        /** @var $componentOrType ItemAttr|ItemAttrAlias */
                        if ($this->isComponentAlias($componentOrType)) {
                            $queryAliasesColumn[] = '\'"' . $componentOrType::getAttrPk() . '":\', ' . $db->quote($componentOrType::getPk());
                        }
                    }
                }

                $baseColumnsToSelect[] = 'CONCAT(\',"aliases":{\', ' . implode(', \',\', ', $queryAliasesColumn) . ', \'}\') AS ' . $db->quote('meta_add');
            } else {
                $baseColumnsToSelect[] = '\'\' AS ' . $db->quote('meta_add');
            }

            $queryBaseColumnsToSelect = implode(', ', $baseColumnsToSelect);

            $queryTablesToSelect = [];
            $queryTablesToSelect[] = $db->quote($this->itemTable);
            $queryTablesToSelect = array_merge($queryTablesToSelect, array_map(function ($component) use ($db, $aliases) {
                /** @var $component ItemAttr|ItemAttrAlias */
                if ($aliases && $this->isComponentAlias($component)) {
                    $attrTable = $component::getAttrTable();
                    $table = $component::getTable();
                } else {
                    $attrTable = $component::getTable();
                    $table = $attrTable;
                }

                if (Category::getTable() == $attrTable) {
                    return $db->quote(CategoryChild::getTable()) . ', ' . $db->quote($table);
                }

                return $db->quote($table);
            }, $componentsToSelect));

            $mvaComponentsToSelect = array_filter($componentsToSelect, function ($component) use ($mvaComponents) {
                return in_array($component, $mvaComponents);
            });

            $queryTablesToSelect = array_merge($queryTablesToSelect, array_map(function ($component) use ($db, $aliases) {
                /** @var $component ItemAttr|ItemAttrAlias */
                $attrTable = $aliases && $this->isComponentAlias($component) ? $component::getAttrTable() : $component::getTable();
                return $db->quote('item_' . $attrTable);
            }, $mvaComponentsToSelect));

            $queryTablesToSelect = implode(', ', $queryTablesToSelect);

            //sync with SRC::getWhere
            $queryWhere = [
                $db->quote('is_in_stock', $this->itemTable) . ' = ' . Entity::normalizeBool(true),
            ];

            //sync with SRC::getWhere
            if (!in_array(URI::SPORT, $typesToSelect) && !in_array(Tag::class, $componentsToSelect)) {
                $queryWhere[] = $db->quote('is_sport', $this->itemTable) . ' = ' . Entity::normalizeBool(false);
            }

            if (!in_array(URI::SIZE_PLUS, $typesToSelect) && !in_array(Tag::class, $componentsToSelect)) {
                $queryWhere[] = $db->quote('is_size_plus', $this->itemTable) . ' = ' . Entity::normalizeBool(false);
            }

            $queryWhere = array_merge($queryWhere, array_map(function ($type) use ($db, $typesColumns) {
                if (URI::SALES === $type) {
                    return $db->quote('old_price', $this->itemTable) . ' > 0';
                }

                return $db->quote($typesColumns[$type], $this->itemTable) . ' = ' . Entity::normalizeBool(true);
            }, $typesToSelect));

            $queryWhere = array_merge($queryWhere, array_map(function ($component) use ($db, $mvaComponents, $aliases) {
                /** @var $component ItemAttr|ItemAttrAlias */
                if ($aliases && $this->isComponentAlias($component)) {
                    $attrPk = $component::getAttrPk();
                    $attrTable = $component::getAttrTable();
                    $table = $component::getTable();
                } else {
                    $attrPk = $component::getPk();
                    $attrTable = $component::getTable();
                    $table = $attrTable;
                }

                $itemPk = Item::getPk();

                $where = [];

                //@todo if alias and component has is_active column - join and make condition
                if (array_key_exists('is_active', $component::getColumns())) {
                    $where[] = $db->quote('is_active', $table) . ' = ' . $component::normalizeBool(true);
                }

                if (in_array($component, $mvaComponents)) {
                    $where[] = $db->quote($itemPk, $this->itemTable) . ' = ' . $db->quote(Item::getPk(), 'item_' . $attrTable);
                    $where[] = $db->quote($attrPk, 'item_' . $attrTable) . ' = ' . $db->quote($attrPk, $table);
                    return implode(' AND ', $where);
                }

                if (Category::getTable() == $attrTable) {
                    $where[] = $db->quote($attrPk, $this->itemTable) . ' = ' . $db->quote('child_category_id', CategoryChild::getTable());
                    $where[] = $db->quote(Category::getPk(), CategoryChild::getTable()) . ' = ' . $db->quote(Category::getPk(), $table);
                    return implode(' AND ', $where);
                }

                $where[] = $db->quote($attrPk, $this->itemTable) . ' = ' . $db->quote($attrPk, $table);

                return implode(' AND ', $where);
            }, $componentsToSelect));

            $queryWhere = implode(' AND ', $queryWhere);

            $queryGroup = [];

            $queryGroup = array_merge($queryGroup, array_map(function ($component) use ($db, $aliases) {
                /** @var $component ItemAttr|ItemAttrAlias */
                if ($aliases && $this->isComponentAlias($component)) {
                    $attrPk = $component::getAttrPk();
                    $attrTable = $component::getAttrTable();
                    $table = $component::getTable();
                } else {
                    $attrPk = $component::getPk();
                    $attrTable = $component::getTable();
                    $table = $attrTable;
                }

                if (Category::getTable() == $attrTable) {
                    return $db->quote($attrPk, CategoryChild::getTable());
                }

                return $db->quote($attrPk, $table);
            }, $componentsToSelect));

            $queryGroup = implode(', ', $queryGroup);

            $queryHaving = [];

            if ($checkCount) {
                $queryHaving[] = $db->quote('cnt') . ' > 0';
            }

            $queryHaving = implode(' ', $queryHaving);

            $query = implode(' ', [
                'INSERT IGNORE INTO',
                $db->quote($this->catalogTable) . ' (' . $queryColumnsToInsert . ')',
//                '(',
                'SELECT ' . $queryColumnsToSelect,
                'FROM (',
                'SELECT ' . $queryBaseColumnsToSelect,
                'FROM ' . $queryTablesToSelect,
                $queryWhere ? ('WHERE ' . $queryWhere) : '',
                $queryGroup ? ('GROUP BY ' . $queryGroup) : '',
                $queryHaving ? ('HAVING ' . $queryHaving) : '',
                ') AS ' . $db->quote('t'),
            ]);

            try {
//                $this->app->container->logger->make($query);
                $aff += $db->req($query)->affectedRows();
            } catch (Throwable $e) {
                $this->app->container->logger->error($e);
            }
        }

        return $aff;
    }

    /**
     * @param array $componentsAndTypes
     * @param bool $aliases
     * @return array
     */
    private function orderAccordingPagePath(array $componentsAndTypes, $aliases = false)
    {
        $output = [];

        foreach (URI::getPagePath([], true) as $uri) {
            foreach ($componentsAndTypes as $componentOrType) {
                if ($componentOrType == $uri) {
                    $output[] = $componentOrType;
                } elseif (class_exists($componentOrType)) {
                    /** @var $componentOrType ItemAttr|ItemAttrAlias */
                    if ($aliases && $this->isComponentAlias($componentOrType)) {
                        $table = $componentOrType::getAttrTable();
                    } else {
                        $table = $componentOrType::getTable();
                    }

                    if ($table == $uri) {
                        $output[] = $componentOrType;
                    }
                }
            }
        }

        return $output;
    }

    /**
     * @param ItemAttr[] $components
     * @return ItemAttr[]|ItemAttrAlias[]
     */
    private function replaceWithAliases(array $components)
    {
        $output = [];

        foreach ($components as $component) {
            $component = $component::getClass();
            $componentAlias = $component . '\\Alias';

            $output[] = Classes::isExists($componentAlias, $this->app) ? $componentAlias : $component;
        }

        return $output;
    }

    private function isComponentAlias($componentOrComponentAliasOrType)
    {
        return false !== strpos($componentOrComponentAliasOrType, '\\Alias');
    }
}