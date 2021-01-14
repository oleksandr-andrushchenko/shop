<?php

namespace SNOWGIRL_SHOP\Catalog\SRC\DataProvider;

use SNOWGIRL_CORE\Elasticsearch\ElasticsearchQuery;
use SNOWGIRL_SHOP\Catalog\SRC\DataProvider;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Item\IndexerHelper;

class ElasticsearchDataProvider extends DataProvider
{
    public function getColumnsMapping(array $columns = null): array
    {
        $output = [];

        if (null === $columns) {
            $columns = array_keys($this->src->getEntities());
        }

        $ajaxSuggestionsAttrManagers = IndexerHelper::getAjaxSuggestionsAttrPkToTable($this->src->getURI()->getApp());

        foreach ($columns as $attrPk) {
            if (array_key_exists($attrPk, $ajaxSuggestionsAttrManagers)) {
                $output[$attrPk] = $ajaxSuggestionsAttrManagers[$attrPk] . '.id';
            } else {
                $output[] = $attrPk;
            }
        }

        return $output;
    }

    public function getItemsAttrs(): array
    {
        $mappings = $this->getColumnsMapping();

        $index = $this->src->getURI()->getApp()->managers->items->getEntity()->getTable();
        $query = new ElasticsearchQuery();

        $query->queryBoolFilter = array_values($this->getWhere());
        $query->from = $this->src->getOffset();
        $query->size = $this->src->getLimit();
        $query->sort = $this->getOrder();
        $query->sourceIncludes = $mappings;
        $query->sourceIncludes = array_values($mappings);
        $query->idsAsKeys = true;

        $items = $this->src->getURI()->getApp()->container
            ->elasticsearch($this->src->getMasterServices())
            ->search($index, $query);

        $output = [];

        foreach ($items as $id => $item) {
            $tmp = $item;
            $tmp['item_id'] = $id;

            foreach ($mappings as $k => $v) {
                if (is_string($k)) {
                    $tmp2 = $item;

                    foreach (explode('.', $v) as $peace) {
                        if (array_key_exists($peace, $tmp2)) {
                            $tmp2 = $tmp2[$peace];
                        } else {
                            $tmp2 = null;
//                            $this->src->getURI()->getApp()->container->logger->warning("'$peace' peace key not found", [
//                                'item' => $item,
//                                'column_key' => $k,
//                                'column_value' => $v,
//                            ]);
                            break;
                        }
                    }


                    $tmp[$k] = $tmp2;
                }
            }

            $output[] = $tmp;
        }

        return $output;
    }

    public function getWhere(bool $raw = false): array
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

        $ajaxSuggestionsAttrPkToTable = IndexerHelper::getAjaxSuggestionsAttrPkToTable($this->src->getURI()->getApp());

        foreach (array_merge($sva, $mva) as $pk => $table) {
            if (isset($params[$pk])) {
                $v = $params[$pk];

                if (isset($ajaxSuggestionsAttrPkToTable[$pk])) {
                    $key = $ajaxSuggestionsAttrPkToTable[$pk] . '.id';

                    if (is_array($v)) {
                        $tmp = ['terms' => [$key => array_map(function ($vv) {
                            return (int) $vv;
                        }, $v)]];
                    } else {
                        $tmp = ['term' => [$key => (int) $v]];
                    }

                    if (isset($mva[$pk])) {
                        $output[$pk] = [
                            'nested' => [
                                'path' => $ajaxSuggestionsAttrPkToTable[$pk],
                                'query' => [
                                    'bool' => [
                                        'filter' => [
                                            $tmp,
                                        ],
                                    ],
                                ],
                            ],
                        ];
                    } else {
                        $output[$pk] = $tmp;
                    }
                } else {
                    if (is_array($v)) {
                        $output[$pk] = ['terms' => [$pk => array_map(function ($vv) {
                            return (int) $vv;
                        }, $v)]];
                    } else {
                        $output[$pk] = ['term' => [$pk => (int) $v]];
                    }
                }
            }
        }

        if (isset($output['category_id'])) {
            $categoryId = (int) $params['category_id'];
            $output['category_id'] = ['terms' => ['category_id' => $this->src->getURI()->getApp()->managers->categories->getChildrenIdFor($categoryId)]];
//            $output['category_id'] = ['term' => ['category_id' => $categoryId]];
        }

        if (isset($params[URI::PRICE_FROM])) {
            $output[URI::PRICE_FROM] = ['range' => ['price' => ['gt' => (int) $params[URI::PRICE_FROM]]]];
        }

        if (isset($params[URI::PRICE_TO])) {
            $output[URI::PRICE_TO] = ['range' => ['price' => ['lte' => (int) $params[URI::PRICE_TO]]]];
        }

        if (isset($params[URI::SALES])) {
            $output[URI::SALES] = ['term' => ['is_sales' => true]];
        }

//        if (!isset($params[URI::PRICE_FROM]) && !isset($params[URI::PRICE_TO])) {
        //@todo default range (economy)
//        }

        return $output;
    }

    public function getOrder(bool $cache = false): array
    {
        $output = [];

        $info = $this->src->getOrderInfo();

        if ($cache) {
            //this column should be enumerated according to this method non-cache output
            $output[$info->cache_column] = SORT_ASC;
            return $output;
        }

//        $output[] = 'created_at:desc';
//        $output[] = 'partner_updated_at:desc';

        if ($info->column) {
            $output[$info->column] = $info->order ? SORT_DESC : SORT_ASC;
        }

//        $output[] = 'created_at:desc';
//        $output[] = 'partner_updated_at:desc';

//        if ('rating' !== $info->column) {
//            $output[] = 'rating:desc';
//        }

//        $output[] = 'item_id:desc';

        return $output;
    }

    public function getTotalCount(): int
    {
        $index = $this->src->getURI()->getApp()->managers->items->getEntity()->getTable();
        $query = new ElasticsearchQuery();

        $query->queryBoolFilter = array_values($this->getWhere());

        return $this->src->getURI()->getApp()->container->elasticsearch($this->src->getMasterServices())
            ->count($index, $query);
    }
}