<?php

namespace SNOWGIRL_SHOP\Manager\Item\Attr\DataProvider;

use SNOWGIRL_CORE\Elasticsearch\ElasticsearchQuery;
use SNOWGIRL_CORE\Exception;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Brand;
use SNOWGIRL_SHOP\Entity\Color;
use SNOWGIRL_SHOP\Entity\Material;
use SNOWGIRL_SHOP\Manager\Item\Attr\DataProvider;
use SNOWGIRL_CORE\Helper\Arrays;

class ElasticsearchDataProvider extends DataProvider
{
    use \SNOWGIRL_CORE\Manager\DataProvider\Traits\ElasticsearchDataProvider;

    /**
     * @param URI $uri
     * @param string|null $query
     * @param bool $prefix
     * @return array
     * @throws Exception
     * @todo implement prefix support
     */
    public function getFiltersCountsByUri(URI $uri, string $query = null, bool $prefix = false): array
    {
        $pk = $this->manager->getEntity()->getPk();
//        $table = $this->manager->getEntity()->getTable();
        $itemTable = $this->manager->getApp()->managers->items->getEntity()->getTable();

        $where = $uri->getSRC()->getDataProvider('elasticsearch')->getWhere();

        if ($this->manager->getQuery()->where) {
            $where = array_merge($where, Arrays::cast($this->manager->getQuery()->where));
        }

        $sva = $this->manager->getApp()->managers->catalog->getSvaPkToTable();
        $mva = $this->manager->getApp()->managers->catalog->getMvaPkToTable();

        if (isset($sva[$pk])) {
            unset($where[$pk]);
            $table = $sva[$pk];
        } elseif (isset($mva[$pk])) {
            unset($where[$pk]);
            $table = $mva[$pk];
        } else {
            throw new Exception('unknown attr entity');
        }

        $params = [];
        $params['size'] = 0;
        $params['body'] = [];

        $path = [];
        $path[] = 'aggregations';

        $queryBoolFilter = array_values($where);

        $ajaxSuggestionsAttrManagers = [
            Brand::getPk(),
            Color::getPk(),
            Material::getPk(),
        ];

        $size = $this->manager->getQuery()->limit ? (int) $this->manager->getQuery()->limit : 999999;

        if (in_array($pk, $ajaxSuggestionsAttrManagers)) {
            $key = $table . '.id';

            if ($query) {
                if (isset($sva[$pk])) {
                    $queryBoolFilter[] = ['prefix' => [$table . '.name' => $query]];
                    $params['body']['aggs'] = [
                        $key => [
                            'terms' => [
                                'field' => $key,
                                'order' => ['_count' => 'desc'],
                                'size' => $size,
                            ],
                        ],
                    ];
                    $path[] = $key;
                } elseif (isset($mva[$pk])) {
                    $nestedQueryBoolFilter = [];
                    $nestedQueryBoolFilter[] = ['prefix' => [$table . '.name' => $query]];
                    $params['body']['aggs'] = [
                        $table => [
                            'nested' => [
                                'path' => $table,
                            ],
                            'aggs' => [
                                'filtered' => [
                                    'filter' => [
                                        'bool' => [
                                            'filter' => $nestedQueryBoolFilter,
                                        ],
                                    ],
                                    'aggs' => [
                                        $key => [
                                            'terms' => [
                                                'field' => $key,
                                                'order' => ['_count' => 'desc'],
                                                'size' => $size,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ];

                    $path[] = $table;
                    $path[] = 'filtered';
                    $path[] = $key;
                }
            } else {
                if (isset($sva[$pk])) {
                    $params['body']['aggs'] = [
                        $key => [
                            'terms' => [
                                'field' => $key,
                                'order' => ['_count' => 'desc'],
                                'size' => $size,
                            ],
                        ],
                    ];
                    $path[] = $key;
                } elseif (isset($mva[$pk])) {
                    $params['body']['aggs'] = [
                        $table => [
                            'nested' => [
                                'path' => $table,
                            ],
                            'aggs' => [
                                $key => [
                                    'terms' => [
                                        'field' => $key,
                                        'order' => ['_count' => 'desc'],
                                        'size' => $size,
                                    ],
                                ],
                            ],
                        ],
                    ];

                    $path[] = $table;
                    $path[] = $key;
                }
            }
        } else {
            $key = $pk;
            $params['body']['aggs'] = [
                $key => [
                    'terms' => [
                        'field' => $key,
                        'order' => ['_count' => 'desc'],
                        'size' => $size,
                    ],
                ],
            ];
            $path[] = $key;
        }

        $params['body']['query'] = [
            'bool' => [
                'filter' => $queryBoolFilter,
            ],
        ];

        $path[] = 'buckets';

        $query = new ElasticsearchQuery();
        $query->paths = $path;

        $output = $this->manager->getApp()->container->elasticsearch($this->manager->getMasterServices())
            ->searchByParams($itemTable, $params, $query);

        return array_map(function ($item) use ($pk) {
            return [
                $pk => $item['key'],
                'cnt' => $item['doc_count']
            ];
        }, $output);
    }
}