<?php

namespace SNOWGIRL_SHOP\SEO;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Service\Logger;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalog;
use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalogManager;
use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\Size;
use SNOWGIRL_SHOP\Entity\Tag;
use SNOWGIRL_SHOP\Catalog\SRC as CatalogSRC;
use SNOWGIRL_SHOP\Catalog\SEO as CatalogSEO;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Helper\Classes;
use SNOWGIRL_SHOP\Entity\Category\Child as CategoryChild;
use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttr;
use SNOWGIRL_SHOP\Entity\Item\Attr\Alias as ItemAttrAlias;
use SNOWGIRL_SHOP\SEO;

/**
 * @todo    ... test & fix
 * Class Pages
 * @property SEO seo
 * @package SNOWGIRL_SHOP\SEO
 */
class Pages extends \SNOWGIRL_CORE\SEO\Pages
{
    protected $components;
    protected $mvaComponents;
    protected $types;
    protected $itemTable;

    protected function initialize()
    {
        $this->components = PageCatalogManager::getComponentsOrderByRdbmsKey();
        $this->mvaComponents = PageCatalogManager::getMvaComponents();
        $this->types = URI::TYPE_PARAMS;
        $this->itemTable = Item::getTable();

        return $this;
    }

    public function update()
    {
        $this->seo->getApp()->services->rdbms->truncateTable(PageCatalog::getTable());

        $aliases = $this->seo->getApp()->config->catalog->aliases(false);

        $this->generateCatalogPages(false, !$aliases);

        if ($aliases) {
            $this->generateCatalogPages(true, $aliases);
        }

        return true;
    }

    /**
     * @param bool $aliases
     * @param bool $ftdbms
     *
     * @return bool
     */
    protected function generateCatalogPages($aliases = false, $ftdbms = true)
    {
        $this->log(__FUNCTION__ . '...');

        $this->seo->getApp()->managers->categories->syncTree();

        $db = $this->seo->getApp()->services->rdbms;

        $components = $this->components;
        $mvaComponents = $this->mvaComponents;

        if ($aliases) {
            $components = $this->replaceWithAliases($components);

            $tmp = array_filter($components, function ($component) {
                return $this->isComponentAlias($component);
            });

            if (0 == count($tmp)) {
                return true;
            }

            $mvaComponents = $this->replaceWithAliases($mvaComponents);
        }

        $typesColumns = CatalogSRC::getTypesToColumns();
        $typesTexts = CatalogSEO::getTypesToTexts();

        $componentsAndTypes = array_merge($components, $this->types);

        $aliasPostfix = '\\Alias';

        $combinations = array_filter(Arrays::getUniqueCombinations($componentsAndTypes), function ($combination) use ($aliasPostfix, $aliases) {
            if (in_array(Size::class, $combination) || in_array(Size::class . $aliasPostfix, $combination)) {
                return false;
            }

            //if non-type and no have category
            if (0 == count(array_intersect($combination, $this->types))) {
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
                if (0 == count(array_diff($combination, $this->types))) {
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
            'meta' => 'CONCAT(\'{"count":\', ' . $db->quote('cnt') . ', ' . $db->quote('meta_add') . ', \'}\')'
        ];

        //'meta' => 'CONCAT(\'{"count":\', ' . $db->quote('cnt') . ', ' . $db->quote('meta_add') . ', \'}\')'
        //'{"count":20,"aliases":{"category_id":2,"brand_id":5}}'

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
            $this->log('#' . $j . ' ouf of ' . $s . '...');
            $j++;

            $componentsAndTypesToSelect = array_values(array_filter($componentsAndTypes, function ($componentOrType) use ($combination) {
                return in_array($componentOrType, $combination);
            }));

            $componentsToSelect = array_values(array_filter($componentsAndTypesToSelect, function ($componentOrType) use ($components) {
                return in_array($componentOrType, $components);
            }));

            $checkCount = 0 == count($componentsToSelect);

            $typesToSelect = array_filter($componentsAndTypesToSelect, function ($componentOrType) {
                return in_array($componentOrType, $this->types);
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
                } elseif (in_array($componentOrType, $this->types)) {
                    $queryParamsColumn[] = '\'"' . $componentOrType . '":1\'';
                } else {
                    $this->log('undefined component or type[' . $componentOrType . ']', Logger::TYPE_ERROR);
                    return false;
                }
            }

            $mapName = function ($componentOrType) use ($combination, $db, $components, $typesTexts) {
                if (in_array($componentOrType, $components)) {
                    /** @var $componentOrType ItemAttr|ItemAttrAlias */
                    $table = $componentOrType::getTable();

                    if (array_key_exists('name_multiply', $componentOrType::getColumns())) {
                        $tmp = $db->quote('name_multiply', $table);
                        return 'IF(' . $tmp . ' IS NULL OR ' . $tmp . ' = \'\', ' . $db->quote('name', $table) . ', ' . $tmp . ')';
                    }

                    return $db->quote('name', $table);
                } elseif (in_array($componentOrType, $this->types)) {
                    return '\'' . $typesTexts[$componentOrType] . '\'';
                } else {
                    $this->log('undefined component or type[' . $componentOrType . ']', Logger::TYPE_ERROR);
                    throw new \Exception;
                }
            };

            //do not change  this logic, coz of page_catalog_custom.uri_hash link
            $mapUri = function ($componentOrType) use ($combination, $db, $components) {
                if (in_array($componentOrType, $components)) {
                    /** @var $componentOrType ItemAttr|ItemAttrAlias */
                    return $db->quote('uri', $componentOrType::getTable());
                } elseif (in_array($componentOrType, $this->types)) {
                    return '\'' . $componentOrType . '\'';
                } else {
                    $this->log('undefined component or type[' . $componentOrType . ']', Logger::TYPE_ERROR);
                    throw new \Exception;
                }
            };

            $expr = [
                'name' => ((1 < count($componentsAndTypesToSelect))
                    ? ('CONCAT(' . implode(', \' \', ', array_map($mapName, $componentsAndTypesToSelect)) . ')')
                    : $mapName($componentsAndTypesToSelect[0])),
                'uri' => (1 < count($componentsAndTypesToSelect)
                    ? ('CONCAT(' . implode(', \'/\', ', array_map($mapUri, $this->orderAccordingPagePath($componentsAndTypesToSelect, $aliases))) . ')')
                    : $mapUri($componentsAndTypesToSelect[0])),
                'params' => 'CONCAT(\'{\', ' . implode(', \',\', ', $queryParamsColumn) . ', \'}\')'
            ];

            $baseColumnsToSelect = [];

            foreach ($columnsToInsert as $column) {
                if (isset($expr[$column])) {
                    $baseColumnsToSelect[] = $expr[$column] . ' AS ' . $db->quote($column);
                } else {
                    $this->log('undefined column expr[' . $column . ']', Logger::TYPE_ERROR);
                    return false;
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

                //'meta' => 'CONCAT(\'{"count":\', ' . $db->quote('cnt') . ', ' . $db->quote('meta_add') . ', \'}\')'
                //'{"count":20,"aliases":{"category_id":2,"brand_id":5}}'

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
            $queryWhere = [];
            //@todo uncomment if do not shows out-of-stocks in catalog...
//            $queryWhere[] = $db->quote('is_in_stock', $this->itemTable) . ' = 1';

            //sync with SRC::getWhere
            if (!in_array(URI::SPORT, $typesToSelect) && !in_array(Tag::class, $componentsToSelect)) {
                $queryWhere[] = $db->quote('is_sport', $this->itemTable) . ' = 0';
            }

            if (!in_array(URI::SIZE_PLUS, $typesToSelect) && !in_array(Tag::class, $componentsToSelect)) {
                $queryWhere[] = $db->quote('is_size_plus', $this->itemTable) . ' = 0';
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
                $db->quote(PageCatalog::getTable()) . ' (' . $queryColumnsToInsert . ')',
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
//                $this->seo->getApp()->services->logger->make($query);
                $db->req($query);
            } catch (\Exception $ex) {
                $this->seo->getApp()->services->logger->makeException($ex);
            }
        }

        if ($ftdbms && PageCatalog::isFtdbmsIndex()) {
//            $this->seo->getApp()->services->ftdbms->rotate($this->seo->getApp(), PageCatalog::class);
            $this->seo->getApp()->utils->catalog->doIndexFtdbms();
        }

        return true;
    }

    /**
     * @param array $componentsAndTypes
     * @param bool  $aliases
     *
     * @return array
     */
    protected function orderAccordingPagePath(array $componentsAndTypes, $aliases = false)
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
     *
     * @return ItemAttr[]|ItemAttrAlias[]
     */
    protected function replaceWithAliases(array $components)
    {
        $output = [];

        $app = $this->seo->getApp();

        foreach ($components as $component) {
            $component = $component::getClass();
            $componentAlias = $component . '\\Alias';

            $output[] = Classes::isExists($componentAlias, $app) ? $componentAlias : $component;
        }

        return $output;
    }

    protected function isComponent($componentOrComponentAliasOrType)
    {
        return in_array($componentOrComponentAliasOrType, $this->components);
    }

    protected function isType($componentOrComponentAliasOrType)
    {
        return in_array($componentOrComponentAliasOrType, $this->types);
    }

    protected function isComponentAlias($componentOrComponentAliasOrType)
    {
        return false !== strpos($componentOrComponentAliasOrType, '\\Alias');
    }
}