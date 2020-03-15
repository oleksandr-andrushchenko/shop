<?php

namespace SNOWGIRL_SHOP\Catalog\SRC\DataProvider;

use SNOWGIRL_SHOP\Catalog\SRC\DataProvider;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Item;

class Indexer extends DataProvider
{
    protected function getAjaxSuggestionsAttrPkToTable(): array
    {
        $manager = $this->src->getURI()->getApp()->managers;

        return [
            ($entity = $manager->brands->getEntity())->getPk() => $entity->getTable(),
            ($entity = $manager->colors->getEntity())->getPk() => $entity->getTable(),
            ($entity = $manager->materials->getEntity())->getPk() => $entity->getTable(),
        ];
    }

    public function getColumnsMapping(array $columns = null)
    {
        $output = [];

        if (null === $columns) {
            $columns = array_keys($this->src->getEntities());
        }

        $ajaxSuggestionsAttrManagers = $this->getAjaxSuggestionsAttrPkToTable();

        foreach ($columns as $attrPk) {
            if (array_key_exists($attrPk, $ajaxSuggestionsAttrManagers)) {
                $output[$attrPk] = $ajaxSuggestionsAttrManagers[$attrPk] . '.id';
            } else {
                $output[] = $attrPk;
            }
        }

        return $output;
    }

    public function getItemsAttrs()
    {
        $columns = $this->getColumnsMapping();
        $columns[Item::getPk()] = '_id';

        return $this->src->getURI()->getApp()->container->indexer($this->src->getMasterServices())->search(
            $this->src->getURI()->getApp()->managers->items->getEntity()->getTable(),
            array_values($this->getWhere()),
            $this->src->getOffset(),
            $this->src->getLimit(),
            $this->getOrder(true),
            $columns
        );
    }

    public function getWhere($raw = false)
    {
        $output = [];

        $params = $this->src->getURI()->getParamsByTypes('filter');

        if (isset($params[URI::SPORT])) {
            $output['is_sport'] = ['term' => ['is_sport' => true]];
        } else {
            if (!isset($params['tag_id'])) {
                $output['is_sport'] = ['term' => ['is_sport' => false]];
            }
        }

        if (isset($params[URI::SIZE_PLUS])) {
            $output['is_size_plus'] = ['term' => ['is_size_plus' => true]];
        } else {
            if (!isset($params['tag_id'])) {
                $output['is_size_plus'] = ['term' => ['is_size_plus' => false]];
            }
        }

        $sva = $this->src->getURI()->getApp()->managers->catalog->getSvaPkToTable();
        $mva = $this->src->getURI()->getApp()->managers->catalog->getMvaPkToTable();

        $ajaxSuggestionsAttrPkToTable = $this->getAjaxSuggestionsAttrPkToTable();

        foreach (array_merge($sva, $mva) as $pk => $table) {
            if (isset($params[$pk])) {
                $v = $params[$pk];

                if (isset($ajaxSuggestionsAttrPkToTable[$pk])) {
                    $key = $ajaxSuggestionsAttrPkToTable[$pk] . '.id';

                    if (is_array($v)) {
                        $tmp = ['terms' => [$key => array_map(function ($vv) {
                            return (int)$vv;
                        }, $v)]];
                    } else {
                        $tmp = ['term' => [$key => (int)$v]];
                    }

                    if (isset($mva[$pk])) {
                        $output[$pk] = [
                            'nested' => [
                                'path' => $ajaxSuggestionsAttrPkToTable[$pk],
                                'query' => [
                                    'bool' => [
                                        'filter' => [
                                            $tmp
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    } else {
                        $output[$pk] = $tmp;
                    }
                } else {
                    if (is_array($v)) {
                        $output[$pk] = ['terms' => [$pk => array_map(function ($vv) {
                            return (int)$vv;
                        }, $v)]];
                    } else {
                        $output[$pk] = ['term' => [$pk => (int)$v]];
                    }
                }
            }
        }

        if (isset($output['category_id'])) {
            $categoryId = (int)$params['category_id'];
            $output['category_id'] = ['terms' => ['category_id' => $this->src->getURI()->getApp()->managers->categories->getChildrenIdFor($categoryId)]];
//            $output['category_id'] = ['term' => ['category_id' => $categoryId]];
        }

        if (isset($params[URI::PRICE_FROM])) {
            $output[URI::PRICE_FROM] = ['range' => ['price' => ['gt' => (int)$params[URI::PRICE_FROM]]]];
        }

        if (isset($params[URI::PRICE_TO])) {
            $output[URI::PRICE_TO] = ['range' => ['price' => ['lte' => (int)$params[URI::PRICE_TO]]]];
        }

        if (isset($params[URI::SALES])) {
            $output[URI::SALES] = ['term' => ['is_sales' => true]];
        }

//        if (!isset($params[URI::PRICE_FROM]) && !isset($params[URI::PRICE_TO])) {
        //@todo default range (economy)
//        }

        return $output;
    }

    public function getOrder($cache = false)
    {
        $output = [];

        $info = $this->src->getOrderInfo();

        if (true || $cache) {
            //this column should be enumerated according to this method non-cache output
            $output[] = $info->cache_column . ':asc';
            return $output;
        }

//        $output[] = 'is_in_stock:asc';

        $output[] = 'created_at:desc';
        $output[] = 'partner_updated_at:desc';

        if ($info->column) {
            $output[] = $info->column . ':' . ($info->desc ? 'desc' : 'asc');
        }

//        $output[] = 'created_at:desc';
//        $output[] = 'partner_updated_at:desc';

        if ('rating' !== $info->column) {
            $output[] = 'rating:desc';
        }

        $output[] = 'item_id:desc';

        return $output;
    }

    public function getTotalCount()
    {
        return $this->src->getURI()->getApp()->container->indexer($this->src->getMasterServices())->count(
            $this->src->getURI()->getApp()->managers->items->getEntity()->getTable(),
            array_values($this->getWhere())
        );
    }
}