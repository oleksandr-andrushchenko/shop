<?php

namespace SNOWGIRL_SHOP;

use SNOWGIRL_CORE\Request;
use SNOWGIRL_CORE\Helper;
use SNOWGIRL_SHOP\Catalog\URI;

use SNOWGIRL_SHOP\Catalog\URI\Manager as CatalogUriManager;

/**
 * Class Tests
 *
 * @property App app
 * @package SNOWGIRL_SHOP
 */
class Tests extends \SNOWGIRL_CORE\Tests
{
    public function run()
    {
        parent::run();

        $this->testCatalogUriRawOutput();
        $this->testCatalogUriModifiersOutput();

        return true;
    }

    protected function testCatalogUriRawOutput()
    {
        $this->output(__FUNCTION__);

        $masterDomain = $this->app->config->domain->master;
        $prefix = $this->app->config->catalog->add_uri_prefix ? ('/' . URI::CATALOG) : '';

        $data = [
            ['params' => ['path' => 'ryukzaki', 'qwe' => 1], 'domain' => 'master', 'mode' => URI::OUTPUT_DEFINED, 'expected' => $masterDomain . $prefix . '/ryukzaki'],
            ['params' => ['path' => 'ryukzaki', 'qwe' => 1], 'mode' => URI::OUTPUT_DEFINED_SAFE, 'expected' => $prefix . '/ryukzaki'],
            ['params' => ['path' => 'ryukzaki', 'qwe' => 1], 'mode' => URI::OUTPUT_FULL, 'expected' => $prefix . '/ryukzaki?qwe=1'],
            ['params' => ['path' => 'ryukzaki', 'qwe' => 1], 'mode' => URI::OUTPUT_DEFINED, 'expected' => $prefix . '/ryukzaki'],
            ['params' => ['path' => 'ryukzaki', 'qwe' => 1, 'src' => 4], 'mode' => URI::OUTPUT_DEFINED_SAFE, 'expected' => $prefix . '/ryukzaki?src=4'],
            ['params' => ['path' => 'ryukzaki', 'src' => 4, 'qwe' => 1], 'mode' => URI::OUTPUT_FULL, 'expected' => $prefix . '/ryukzaki?src=4&qwe=1'],
//            ['params' => ['path' => 'ryukzaki', URI::QUERY => 'query', 'qwe' => 1], 'mode' => URI::OUTPUT_DEFINED, 'expected' => $prefix . '/ryukzaki?' . URI::QUERY . '=query'],
//            ['params' => ['path' => 'ryukzaki/' . URI::SALES, URI::QUERY => 'query', 'qwe' => 1, 'src' => '4'], 'mode' => URI::OUTPUT_DEFINED_SAFE, 'expected' => $prefix . '/ryukzaki/' . URI::SALES . '?' . URI::QUERY . '=query&src=4'],
//            ['params' => ['path' => 'ochki', URI::QUERY => 'query', 'qwe' => 1, 'src' => 4], 'mode' => URI::OUTPUT_FULL, 'expected' => $prefix . '/ochki?src=4&' . URI::QUERY . '=query&qwe=1'],
            ['params' => ['path' => 'chasy/kawaii-factory', 'brand_id' => 31, 'qwe' => 1, 'src' => 4], 'mode' => URI::OUTPUT_DEFINED, 'expected' => $prefix . '?category_id=83&brand_id[0]=114&brand_id[1]=31'],
            ['params' => ['path' => 'chasy/kawaii-factory', 'brand_id' => 31, 'qwe' => 1, 'src' => 4], 'mode' => URI::OUTPUT_DEFINED_SAFE, 'expected' => $prefix . '?category_id=83&brand_id[0]=114&brand_id[1]=31&src=4'],
            ['params' => ['path' => 'chasy/kawaii-factory', 'brand_id' => 31, 'qwe' => 1, 'src' => 4], 'mode' => URI::OUTPUT_FULL, 'expected' => $prefix . '?category_id=83&brand_id[0]=114&brand_id[1]=31&src=4&qwe=1']
        ];

        $this->runBulk($data, function (array $case) {
            $uriManager = new CatalogUriManager($this->app);
            $request = new Request($this->app);

            if (isset($case['params']['path'])) {
                $request->setPathInfo('/' . $case['params']['path']);
                unset($case['params']['path']);
            }

            $request->setParams($case['params']);

            if (!$uri = $uriManager->createFromRequest($request, $case['domain'] ?? false)) {
                return 'false';
            }

            return $this->sortURL($uri->output($case['mode']));
        }, function (array $case) {
            return $this->sortURL($case['expected']);
        });
    }

    protected function testCatalogUriModifiersOutput()
    {
        $this->output(__FUNCTION__);

        $masterDomain = $this->app->config->domain->master;
        $prefix = $this->app->config->catalog->add_uri_prefix ? ('/' . URI::CATALOG) : '';

        $data = [
            [
                'params' => ['category_id' => 83, 'brand_id' => 31, URI::PAGE_NUM => 2, 'asd' => '123'],
                'domain' => false,
                'modifier' => function (URI $uri) {
                    $uri->set(URI::PAGE_NUM, null)
                        ->inverse('brand_id', 114);
                },
                'mode' => URI::OUTPUT_DEFINED,
                'expected' => $prefix . '?category_id=83&brand_id[0]=114&brand_id[1]=31'
            ],
            [
                'params' => ['category_id' => 78, 'tag_id' => 8, URI::PAGE_NUM => 2, 'asd' => '123', 'src' => 3],
                'domain' => 'master',
                'modifier' => function (URI $uri) {
                    $uri->pop('tag_id', 114);
                },
                'mode' => URI::OUTPUT_FULL,
                'expected' => $masterDomain . $prefix . '/ryukzaki/turisticheskie?' . URI::PAGE_NUM . '=2&src=3&asd=123'
            ],
            [
                'params' => ['category_id' => 78, 'tag_id' => 8, URI::PAGE_NUM => 2, 'asd' => '123', 'src' => '3'],
                'modifier' => function (URI $uri) {
                    $uri->set(URI::PAGE_NUM, 3)
                        ->set(URI::PER_PAGE, 20)
                        ->inverse('tag_id', 9)
                        ->pop('tag_id', 8)
                        ->push('tag_id', 3);
                },
                'mode' => URI::OUTPUT_DEFINED_SAFE,
                'expected' => $prefix . '?category_id=78&' . URI::PAGE_NUM . '=3&src=3&' . URI::PER_PAGE . '=20&tag_id[0]=9&tag_id[1]=3'
            ],
            [
                'params' => ['category_id' => 78, 'brand_id' => 73, 'tag_id' => [8, 3, 9], URI::PRICE_FROM => 1000, URI::PRICE_TO => 2000, URI::PAGE_NUM => 2, URI::ORDER => 'updated_at'],
                'modifier' => function (URI $uri) {
                    $uri->set(URI::PAGE_NUM, null)
                        ->set(URI::EVEN_NOT_STANDARD_PER_PAGE, true)
                        ->set(URI::PER_PAGE, 777)
                        ->inverse('tag_id', 8)
                        ->pop('tag_id', 3)
                        ->set(URI::PRICE_TO, 4000);
                },
                'mode' => URI::OUTPUT_DEFINED_SAFE,
//                'expected' => $prefix . '/ryukzaki/turisticheskie?' . URI::PAGE_NUM . '=3&src=3&' . URI::PER_PAGE . '=20&tag_id[0]=9&tag_id[1]=3'
                'expected' => $prefix . '/ryukzaki/gorodskie/vera-victoria-vito?' . URI::PRICE_FROM . '=1000&' . URI::PRICE_TO . '=4000&' . URI::PER_PAGE . '=777&' . URI::ORDER . '=updated_at'
            ]
        ];

        $this->runBulk($data, function (array $case) {
            $uri = new URI($case['params'], $case['domain'] ?? false);

            if (isset($case['modifier'])) {
                $case['modifier']($uri);
            }

            return $this->sortURL($uri->output($case['mode']));
        }, function (array $case) {
            return $this->sortURL($case['expected']);
        });
    }

    protected function sortURL($url)
    {
        if (($tmp = parse_url($url)) && isset($tmp['query'])) {
            parse_str($tmp['query'], $query);
            ksort($query);

            foreach ($query as $k => &$v) {
                if (is_array($v)) {
                    asort($v);
                    $v = array_values($v);
                }
            }

            $tmp['query'] = urldecode(http_build_query($query));

            return Helper::buildUrlByParts($tmp);
        }

        return $url;
    }
}