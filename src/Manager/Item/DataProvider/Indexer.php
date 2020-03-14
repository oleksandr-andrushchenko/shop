<?php

namespace SNOWGIRL_SHOP\Manager\Item\DataProvider;

use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Item\DataProvider;

class Indexer extends DataProvider
{
    use \SNOWGIRL_CORE\Manager\DataProvider\Traits\Indexer;

    public function getPricesByUri(URI $uri): array
    {
        $where = $uri->getSRC()->getDataProvider('indexer')->getWhere();
        unset($where[URI::PRICE_FROM]);
        unset($where[URI::PRICE_TO]);

        $ranges = [];

        $tmp = $this->manager->getPriceRanges();

        foreach ($tmp as $k => $r) {
            $expr = [
                'key' => 'r_' . $r[0] . '_' . $r[1],
                'from' => (int)$r[0],
                'to' => (int)$r[1]
            ];

            $ranges[$expr['key']] = $expr;
        }

        $body = [
            'aggs' => [
                'filtered' => [
                    'filter' => [
                        'bool' => [
                            'filter' => array_values($where)
                        ]
                    ],
                    'aggs' => [
                        'price_ranges' => [
                            'range' => [
                                'field' => 'price',
                                'keyed' => true,
                                'ranges' => array_values($ranges)
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $params = [
            'size' => 0,
            'body' => $body
        ];

        $raw = $this->manager->getApp()->container->indexer($this->manager->getMasterServices())
            ->searchRaw($this->manager->getEntity()->getTable(), $params, ['aggregations', 'filtered', 'price_ranges', 'buckets']);

        $output = [];

        $tmp = array_combine(array_keys($ranges), array_fill(0, count($ranges), 0));
        $tmp['cnt'] = 0;

        foreach ($raw as $key => $item) {
            if ($item['doc_count'] > 0) {
                $tmp2 = $tmp;
                $tmp2[$key] = 1;
                $tmp2['cnt'] = $item['doc_count'];
                $output[] = $tmp2;
            }
        }

        return $output;
    }

    public function getTypesByUri(URI $uri, &$map = [], &$current = []): array
    {
        $tmp = [];

        foreach ([URI::SALES => 'is_sales', URI::SIZE_PLUS => 'is_size_plus', URI::SPORT => 'is_sport'] as $uriKey => $dbKey) {
            if (in_array($uriKey, URI::TYPE_PARAMS)) {
                $tmp2 = ['aggs' => [
                    $dbKey => [
                        'terms' => [
                            'field' => $dbKey,
                            'size' => 999999
                        ],
                    ]
                ]];

                if ($tmp) {
                    $tmp2['aggs'][$dbKey] = array_merge($tmp2['aggs'][$dbKey], $tmp);
                }

                $tmp = $tmp2;

                $map[$uriKey] = $dbKey;
                $current[$uriKey] = $uri->get($uriKey);
            }
        }

        if (!$map) {
            return [];
        }

        if (3 !== count($map)) {
            throw new \RuntimeException('not supported, fix ::makeCntGroup first');
        }

        $exclude = array_merge($map, array_keys($map));

        $where = array_filter($uri->getSRC()->getDataProvider('indexer')->getWhere(), function ($k) use ($exclude) {
            return !in_array($k, $exclude);
        }, ARRAY_FILTER_USE_KEY);

        $body = [
            'aggs' => [
                'filtered' => [
                    'filter' => [
                        'bool' => [
                            'filter' => array_values($where)
                        ]
                    ]
                ]
            ]
        ];

        $body['aggs']['filtered'] = array_merge($body['aggs']['filtered'], $tmp);

        $params = [
            'size' => 0,
            'body' => $body
        ];

        $raw = $this->manager->getApp()->container->indexer($this->manager->getMasterServices())
            ->searchRaw($this->manager->getEntity()->getTable(), $params);

        return array_filter([
            $this->makeCntGroup($raw, 0, 0, 0),
            $this->makeCntGroup($raw, 0, 0, 1),
            $this->makeCntGroup($raw, 0, 1, 0),
            $this->makeCntGroup($raw, 0, 1, 1),
            $this->makeCntGroup($raw, 1, 0, 0),
            $this->makeCntGroup($raw, 1, 0, 1),
            $this->makeCntGroup($raw, 1, 1, 0),
            $this->makeCntGroup($raw, 1, 1, 1),
        ]);
    }

    protected function makeCntGroup($raw, $x, $y, $z)
    {
        $tmp = $this->walkRawSearchResults($raw, ['aggregations', 'filtered', 'is_sport', 'buckets', $x]);
        $cnt = $this->walkRawSearchResults($tmp, ['is_size_plus', 'buckets', $y, 'is_sales', 'buckets', $z, 'doc_count']);

        if (null !== $cnt) {
            return [
                'is_sport' => $this->walkRawSearchResults($tmp, ['key']),
                'is_size_plus' => $this->walkRawSearchResults($tmp, ['is_size_plus', 'buckets', $y, 'key']),
                'is_sales' => $this->walkRawSearchResults($tmp, ['is_size_plus', 'buckets', $y, 'is_sales', 'buckets', $z, 'key']),
                'cnt' => $cnt
            ];
        }

        return false;
    }

    protected function walkRawSearchResults($output, array $paths)
    {
        foreach ($paths as $path) {
            if (is_array($output) && array_key_exists($path, $output)) {
                $output = $output[$path];
            } else {
                return null;
            }
        }

        return $output;
    }

    protected function getDocsFromAggResult(array $aggResult, array $fields)
    {
        $currentField = $fields[0];
        $buckets = $aggResult[$currentField]['buckets'];

        if (count($fields) == 1) {
            return array_map(function ($bucket) use ($currentField) {
                return [
                    $currentField => $bucket['key'],
                    'cnt' => $bucket['doc_count']
                ];
            }, $buckets);
        }

        $result = [];

        foreach ($buckets as $bucket) {
            $records = $this->getDocsFromAggResult($bucket, array_slice($fields, 1));
            $value = $bucket['key'];

            foreach ($records as &$record) {
                $record[$currentField] = $value;
            }

            $result[] = $records;
        }

        return $result;
    }
}