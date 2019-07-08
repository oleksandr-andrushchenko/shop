<?php

namespace SNOWGIRL_SHOP\Util;

use pQuery\DomNode;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Helper\WalkChunk2;
use SNOWGIRL_CORE\HtmlParser;
use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_CORE\App;

use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalog;
use SNOWGIRL_SHOP\Entity\Page\Catalog\Custom as PageCatalogCustom;

use Google\Cloud\Translate\TranslateClient;
use SNOWGIRL_SHOP\Catalog\URI;

/**
 * Class Catalog
 *
 * @property App app
 * @package SNOWGIRL_SHOP\Util
 */
class Catalog extends Util
{
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
            'updated_at' => 'created_at'
        ];
        $where = new Expr($this->app->services->rdbms->quote('updated_at') . ' IS NOT NULL');

        $output = $this->app->utils->database->doMigrateDataFromTableToTable($tableFrom, $tableTo, $columns, $where);

        return $output;
    }

    protected $translator;

    /**
     * @return TranslateClient
     */
    public function getTranslator()
    {
        if (null === $this->trans) {
            $this->trans = new TranslateClient([
                'key' => $this->app->config->keys->google_api_key,
                'projectId' => $this->app->config->keys->google_cloud_project_id
            ]);
        }

        return $this->trans;
    }

    protected function translate($text, $source = 'en', $target = 'ru')
    {
        if ($this->app->isDev()) {
            return file_get_contents('https://example.com/translate?' . implode('&', [
                    'text=' . urlencode($text),
                    'source=' . urlencode($source),
                    'target=' . urlencode($target)
                ]));
        }

        return $this->translateRaw($text, $source, $target);
    }

    public function translateRaw($text, $source = 'en', $target = 'ru')
    {
        $tmp = $this->getTranslator()->translate($text, [
            'source' => $source,
            'target' => $target
        ]);

        if (is_array($tmp) && isset($tmp['text'])) {
            $table = get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES | ENT_HTML401);

            return strtr($tmp['text'], array_flip(array_merge($table, [
                "'" => '&#39;'
            ])));
        }

        return false;
    }

    /**
     * @param      $content
     * @param bool $link
     *
     * @return HtmlParser
     */
    public function getParser($content, $link = true)
    {
        return $link ? HtmlParser::factoryByLink($content) : HtmlParser::factoryByHtml($content);
    }

    const CATEGORY_CLOTHING = 2;
    const CATEGORY_LINGERIE = 1224;
    const CATEGORY_DRESSES = 413;
    const CATEGORY_SWEATERS = 1001;
    const CATEGORY_COATS = 401;
    const CATEGORY_JACKETS = 432;
    const CATEGORY_JEANS = 514;
    const CATEGORY_PAJAMAS = 461;
    const CATEGORY_PANTS = 512;
    const CATEGORY_SKIRTS = 467;
    const CATEGORY_SUITS = 720;
    const CATEGORY_JACKETS_AND_SUITS = 993;
    const CATEGORY_SWIMWEAR = 481;
    const CATEGORY_TOPS = 418;
    const CATEGORY_ACCESSORIES = 1;
    const CATEGORY_HANDBAGS = 69;
    const CATEGORY_UNDERWEAR = 1224;
    const CATEGORY_SHOES = 3;
    const CATEGORY_BOOTS = 701;
    const CATEGORY_BRAS = 499;
    const CATEGORY_PANTIES = 478;
    const CATEGORY_JEWELERY = 892;
    const CATEGORY_WATCHES = 83;
    const CATEGORY_BLOUSES_AND_SHIRTS = 692;
    const CATEGORY_HOODIES_AND_SWEATSHIRTS = 518;
    const CATEGORY_HOMEWEAR = 717;
    const CATEGORY_JUMPSUITS = 519;
    const CATEGORY_SHORTS = 513;
    const CATEGORY_TROUSERS = 512;
    const CATEGORY_TROUSERS_AND_JEANS = 965;
    const CATEGORY_MAKEUP = 1069;
    const CATEGORY_PERFUME = 748;
    const CATEGORY_BEAUTY = 735;
    const CATEGORY_SUITCASES = 1188;
    const CATEGORY_BACKPACKS = 78;

    const TAG_HEELS = 123;
    const TAG_OCCASION = 18;

    const BRAND_MICHAEL_KORS = 1202;
    const BRAND_LACOSTE = 1863;
    const BRAND_RIVER_ISLAND = 326;
    const BRAND_SUPERDRY = 4150;
    const BRAND_NIKE = 615;
    const BRAND_KURT_GEIGER = 14378;

    const MATERIAL_CASHMERE = 103;
    const MATERIAL_KNITWEAR = 50;

    /**
     * Все ссылки которые содержат id=xxx там внизу есть сео-тексты
     * brands:
     * https://www.macys.com/shop/all-brands/womens?id=63539&cm_sp=intl_hdr-_-women-_-63539_all-women%27s-brands_COL4
     */
    protected function generateTextsMacys()
    {
        $replaceWithSite = ['Macy\'s', 'Macys'];
        $language = 'en';
        $selectorH1 = '#catalogCopyBlock_0 h2';
        $selectorBody = '#catalogCopyBlock_0';

        $bodyNodeFilter = function (DomNode $node) {
            $node->query('h2')[0]->delete();
            $node->remove('.hide-for-large-up');
        };

        return $this->generateTexts($replaceWithSite, $language, $selectorH1, $selectorBody, $bodyNodeFilter, [
            'https://www.macys.com/shop/womens-clothing/dresses?id=5449&cm_sp=intl_hdr-_-women-_-5449_dresses_COL1' => self::CATEGORY_DRESSES,
            'https://www.macys.com/shop/womens-clothing/lingerie?id=225&cm_sp=intl_hdr-_-women-_-225_bras%2C-panties-%26-lingerie_COL1' => self::CATEGORY_LINGERIE,
            'https://www.macys.com/shop/womens-clothing/womens-cashmere-sweaters?id=262&cm_sp=intl_hdr-_-women-_-262_cashmere_COL1' => ['category_id' => self::CATEGORY_SWEATERS, 'material_id' => self::MATERIAL_CASHMERE],
            'https://www.macys.com/shop/womens-clothing/womens-coats?id=269&cm_sp=intl_hdr-_-women-_-269_coats_COL1' => self::CATEGORY_COATS,
            'https://www.macys.com/shop/womens-clothing/womens-jackets?id=120&cm_sp=intl_hdr-_-women-_-120_jackets_COL1' => self::CATEGORY_JACKETS,
            'https://www.macys.com/shop/womens-clothing/womens-jeans?id=3111&cm_sp=intl_hdr-_-women-_-3111_jeans_COL1' => self::CATEGORY_JEANS,
            'https://www.macys.com/shop/womens-clothing/pajamas-and-robes?id=59737&cm_sp=intl_hdr-_-women-_-59737_pajamas%2C-robes-%26-loungewear_COL1' => self::CATEGORY_PAJAMAS,
            'https://www.macys.com/shop/womens-clothing/womens-pants?id=157&cm_sp=intl_hdr-_-women-_-157_pants_COL1' => self::CATEGORY_PANTS,
            'https://www.macys.com/shop/womens-clothing/womens-skirts?id=131&cm_sp=intl_hdr-_-women-_-131_skirts_COL1' => self::CATEGORY_SKIRTS,
            'https://www.macys.com/shop/womens-clothing/womens-suits?id=67592&cm_sp=intl_hdr-_-women-_-67592_suits-%26-suit-separates_COL1' => self::CATEGORY_SUITS,
            'https://www.macys.com/shop/womens-clothing/womens-sweaters?id=260&cm_sp=intl_hdr-_-women-_-260_sweaters_COL1' => self::CATEGORY_SWEATERS,
            'https://www.macys.com/shop/womens-clothing/womens-swimwear?id=8699&cm_sp=intl_hdr-_-women-_-8699_swimwear_COL1' => self::CATEGORY_SWIMWEAR,
            'https://www.macys.com/shop/womens-clothing/womens-tops?id=255&cm_sp=intl_hdr-_-women-_-255_tops_COL1' => self::CATEGORY_TOPS,
            'https://www.macys.com/shop/plus-size-clothing?id=32147&cm_sp=intl_hdr-_-women-_-32147_plus-sizes_COL2' => ['category_id' => self::CATEGORY_CLOTHING, URI::SIZE_PLUS => 1],
            'https://www.macys.com/shop/handbags-accessories?id=26846&cm_sp=intl_hdr-_-women-_-26846_all-handbags-%26-accessories_COL2' => self::CATEGORY_ACCESSORIES,
            'https://www.macys.com/shop/handbags-accessories/handbags?id=27686&cm_sp=intl_hdr-_-women-_-27686_handbags_COL2' => self::CATEGORY_HANDBAGS,
            'https://www.macys.com/shop/handbags-accessories/socks-tights?id=40546&cm_sp=intl_hdr-_-women-_-40546_tights%2C-socks%2C-%26-hosiery_COL2' => self::CATEGORY_UNDERWEAR,
            'https://www.macys.com/shop/shoes/all-womens-shoes?id=56233&cm_sp=intl_hdr-_-women-_-56233_all-women%27s-shoes_COL3' => self::CATEGORY_SHOES,
            'https://www.macys.com/shop/shoes/boots?id=25122&cm_sp=intl_hdr-_-women-_-25122_boots_COL3' => self::CATEGORY_BOOTS,
            'https://www.macys.com/shop/shoes/flats?id=50295&cm_sp=intl_hdr-_-women-_-50295_flats_COL3' => self::CATEGORY_SHOES,
            'https://www.macys.com/shop/shoes/high-heels?id=71123&cm_sp=intl_hdr-_-women-_-71123_heels_COL3' => ['category_id' => self::CATEGORY_SHOES, 'tag_id' => self::TAG_HEELS],
            'https://www.macys.com/shop/womens-clothing/bras?id=55799&cm_sp=intl_hdr-_-women-_-55799_bras_COL3' => self::CATEGORY_BRAS,
            'https://www.macys.com/shop/womens-clothing/panties?id=55805&cm_sp=intl_hdr-_-women-_-55805_panties_COL3' => self::CATEGORY_PANTIES,
            'https://www.macys.com/shop/jewelry-watches?id=544&cm_sp=intl_hdr-_-women-_-544_all-jewelry-%26-watches_COL3' => self::CATEGORY_JEWELERY,
            'https://www.macys.com/shop/jewelry-watches/all-fashion-jewelry?id=55352&cm_sp=intl_hdr-_-women-_-55352_fashion-jewelry_COL3' => self::CATEGORY_JEWELERY,
            'https://www.macys.com/shop/jewelry-watches/all-fine-jewelry?id=65993&cm_sp=intl_hdr-_-women-_-65993_fine-jewelry_COL3' => self::CATEGORY_JEWELERY,
            'https://www.macys.com/shop/jewelry-watches/watches?id=23930&cm_sp=intl_hdr-_-women-_-23930_watches_COL3' => self::CATEGORY_WATCHES,
            'https://www.macys.com/shop/womens-clothing/michael-kors-womens-clothing?id=14728&cm_sp=intl_hdr-_-women-_-14728_michael-michael-kors_COL4' => ['category_id' => self::CATEGORY_CLOTHING, 'brand_id' => self::BRAND_MICHAEL_KORS],
            'https://www.macys.com/shop/b/lacoste-shoes?id=71692' => ['category_id' => self::CATEGORY_SHOES, 'brand_id' => self::BRAND_LACOSTE]
        ]);
    }

    protected function generateTextsVery()
    {
        $replaceWithSite = ['Very\'s', 'Very'];
        $language = 'en';
        $selectorH1 = '.footer-copy h2';
        $selectorBody = '.footer-copy';

        $bodyNodeFilter = function (DomNode $node) {
            $node->query('h2')[0]->delete();
        };

        return $this->generateTexts($replaceWithSite, $language, $selectorH1, $selectorBody, $bodyNodeFilter, [
            'https://www.very.co.uk/women/accessories/e/b/1590.end' => self::CATEGORY_ACCESSORIES,
            'https://www.very.co.uk/women/bags-purses/e/b/1591.end' => self::CATEGORY_ACCESSORIES,
            'https://www.very.co.uk/women/river-island/bags-purses/e/b/1591,4294885489.end' => ['category_id' => self::CATEGORY_HANDBAGS, 'brand_id' => self::BRAND_RIVER_ISLAND],
            'https://www.very.co.uk/women/blouses-shirts/e/b/119626.end' => self::CATEGORY_BLOUSES_AND_SHIRTS,
            'https://www.very.co.uk/women/coats-jackets/e/b/1642.end' => self::CATEGORY_COATS,
            'https://www.very.co.uk/women/dresses/e/b/1655.end' => self::CATEGORY_DRESSES,
            'https://www.very.co.uk/women/hoodies-sweatshirts/e/b/104421.end' => self::CATEGORY_HOODIES_AND_SWEATSHIRTS,
            'https://www.very.co.uk/women/jeans/e/b/1682.end' => self::CATEGORY_JEANS,
            'https://www.very.co.uk/women/knitwear/e/b/1699.end' => ['category_id' => self::CATEGORY_CLOTHING, 'material_id' => self::MATERIAL_KNITWEAR],
            'https://www.very.co.uk/women/lingerie/e/b/1710.end' => self::CATEGORY_LINGERIE,
            'https://www.very.co.uk/women/nightwear-loungewear/e/b/1729.end' => self::CATEGORY_HOMEWEAR,
            'https://www.very.co.uk/women/occasion-wear/e/b/1630.end' => ['category_id' => self::CATEGORY_CLOTHING, 'tag_id' => self::TAG_OCCASION],
            'https://www.very.co.uk/women/playsuits-jumpsuits/e/b/11103.end' => self::CATEGORY_JUMPSUITS,
            'https://www.very.co.uk/women/shoes-boots/e/b/1665.end' => self::CATEGORY_SHOES,
            'https://www.very.co.uk/women/shorts/e/b/1794.end' => self::CATEGORY_SHORTS,
            'https://www.very.co.uk/women/skirts/e/b/1738.end' => self::CATEGORY_SKIRTS,
            'https://www.very.co.uk/women/sportswear/e/b/1803.end' => ['category_id' => self::CATEGORY_CLOTHING, URI::SPORT => 1],
            'https://www.very.co.uk/women/swimwear-beachwear/e/b/1757.end' => self::CATEGORY_SWIMWEAR,
            'https://www.very.co.uk/women/tops-t-shirts/e/b/1776.end' => self::CATEGORY_TOPS,
            'https://www.very.co.uk/women/trainers/e/b/100306.end' => ['category_id' => self::CATEGORY_SHOES, URI::SPORT => 1],
            'https://www.very.co.uk/women/trousers-leggings/e/b/104437.end' => self::CATEGORY_TROUSERS_AND_JEANS,
            'https://www.very.co.uk/women/plus-size/e/b/1589,22219.end' => [self::CATEGORY_CLOTHING, URI::SIZE_PLUS => 1],
            'https://www.very.co.uk/women/e/b/1589.end' => self::CATEGORY_CLOTHING,
            'https://www.very.co.uk/women/river-island/e/b/1589,4294885489.end' => ['brand_id' => self::BRAND_RIVER_ISLAND],
            'https://www.very.co.uk/women/superdry/e/b/1589,4294959872.end' => ['brand_/id' => self::BRAND_SUPERDRY],
            'https://www.very.co.uk/women/nike/e/b/1589,175.end' => ['brand_id' => self::BRAND_NIKE],
            'https://www.very.co.uk/women/kurt-geiger/e/b/1589,4294889181.end' => ['brand_id' => self::BRAND_KURT_GEIGER],
            'https://www.very.co.uk/gifts-jewellery/ladies-watches/e/b/112836.end' => self::CATEGORY_WATCHES,
            'https://www.very.co.uk/beauty/make-up/e/b/100161.end' => self::CATEGORY_MAKEUP,
            'https://www.very.co.uk/beauty/perfume/e/b/100152.end' => self::CATEGORY_PERFUME,
            'https://www.very.co.uk/beauty/beauty-gift-sets/e/b/100180.end' => self::CATEGORY_BEAUTY,
            'https://www.very.co.uk/sports-leisure/womens-sports-shoes/e/b/2898.end' => ['category_id' => self::CATEGORY_SHOES, URI::SPORT => 1],
            'https://www.very.co.uk/sports-leisure/luggage/e/b/3132.end' => self::CATEGORY_SUITCASES,
            'https://www.very.co.uk/sports-leisure/bags-backpacks/e/b/3008.end' => self::CATEGORY_BACKPACKS
        ]);
    }

    protected function generateTexts($replaceWithSite, $language, $selectorH1, $selectorBody, \Closure $bodyNodeFilter, $remoteUriToUriParams)
    {
        $aff = 0;

        $site = ucfirst($this->app->config->site->name);

        foreach ($remoteUriToUriParams as $remoteUri => $uriParams) {
            if (!$uri = new URI(is_array($uriParams) ? $uriParams : ['category_id' => $uriParams])) {
                $this->output($remoteUri . ': invalid catalog uri');
                continue;
            }

            if (!$page = $this->app->managers->catalog->clear()->getObjectByUri($uri)) {
                $this->output($remoteUri . ': no catalog page');
                continue;
            }

            $custom = $this->app->managers->catalogCustom->getStorageObject()
                ->selectOne($this->app->managers->catalogCustom->getEntity()->getTable(), new Query([
                    'params' => [],
                    'where' => ['params_hash' => $page->getParamsHash()]
                ]));

            if ($custom) {
                $custom = $this->app->managers->catalogCustom->populateRow($custom);
            } else {
                $custom = $this->app->managers->catalog->makeCustom($page);
            }

            $parser = $this->getParser($remoteUri, true);

            if (!$h1 = ($tmp = $parser->query($selectorH1)) ? $tmp[0] : null) {
                $this->output($remoteUri . '[' . $selectorH1 . ']: invalid h1 parse');
                continue;
            }

            if (!$body = ($tmp = $parser->query($selectorBody)) ? $tmp[0] : null) {
                $this->output($remoteUri . '[' . $selectorBody . ']: invalid body parse');
                continue;
            }

            /** @var DomNode $h1 */

            $h1 = $h1->text();
            $h1 = trim($h1);

            /** @var DomNode $body */

            $bodyNodeFilter($body);

            for ($i = 1; $i < 7; $i++) {
                $body->query('h' . $i)->tagName('h3');
            }

            $body = $body->html();
            $body = strip_tags($body, '<p><br><div><h3>');
            $body = preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/i", '<$1$2>', $body);
            $body = trim($body);

            $h1 = str_replace($replaceWithSite, $site, $h1);
            $body = str_replace($replaceWithSite, $site, $body);

            if (!$h1 = $this->translate($h1, $language)) {
                $this->output($remoteUri . ': invalid h1 translate');
                continue;
            }

            if (!$body = $this->translate($body, $language)) {
                $this->output($remoteUri . ': invalid body translate');
                continue;
            }

            $h1 = str_replace($replaceWithSite, $site, $h1);
            $body = str_replace($replaceWithSite, $site, $body);

            $this->output($remoteUri . ':h1: ' . $h1);
            $this->output($remoteUri . ':body: ' . $body);

            $custom->addSeoText([
                'h1' => $h1,
                'body' => $body,
                'user' => 0,
                'active' => 1
            ]);

            if ($this->app->managers->catalogCustom->save($custom)) {
                $aff++;
            }

            $this->output($custom->getId() . '/' . $custom->getParamsHash() . ' created/updated');
        }

        return $aff;
    }

    public function doGenerateTexts()
    {
        $aff = 0;
        $aff += $this->generateTextsMacys();
        $aff += $this->generateTextsVery();
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
        return $this->app->managers->catalog->findColumns(Entity::SEARCH_IN);
    }

    protected function getElasticMappings(): array
    {
        $properties = [
            'uri' => 'text',
            'count' => 'integer',
        ];

        foreach ($this->getSearchColumns() as $column) {
            $properties[$column] = 'text';
            $properties[$column . '_length'] = 'short';
        }

        foreach ($properties as $column => &$type) {
            $type = ['type' => $type];
        }

        return ['properties' => $properties];
    }

    public function doCreateElasticIndex(string $index = null): bool
    {
        $index = $index ?: $this->app->managers->catalog->getEntity()->getTable();

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
        $entity = $this->app->managers->catalog->getEntity();

        $catalogTable = $entity->getTable();
        $catalogPk = $entity->getPk();

        $index = $index ?: $catalogTable;
        $where = Arrays::cast($where);

        $columns = [
            $catalogPk,
            'uri',
            'meta'
        ];

        $searchColumns = $this->getSearchColumns();

        foreach ($searchColumns as $column) {
            $columns[] = $column;
        }

        $columnsOptions = Arrays::filterByKeysArray($entity->getColumns(), $columns);

        $mappingKeys = array_keys($this->getElasticMappings()['properties']);

        $mysql = $this->app->storage->mysql;

        (new WalkChunk2(1000))
            ->setFnGet(function ($lastId, $size) use ($mysql, $catalogPk, $catalogTable, $columns, $where) {
                $query = new Query(['params' => []]);

                if ($lastId) {
                    $where[] = new Expr($mysql->quote($catalogPk, $catalogTable) . ' > ?', $lastId);
                }

                $query->text = implode(' ', [
                    'SELECT ' . implode(', ', array_map(function ($column) use ($mysql, $catalogTable) {
                        return $mysql->quote($column, $catalogTable);
                    }, $columns)),
                    'FROM ' . $mysql->quote($catalogTable),
                    $where ? $mysql->makeWhereSQL($where, $query->params, $catalogTable) : '',
                    $mysql->makeOrderSQL([$catalogPk => SORT_ASC], $query->params, $catalogTable),
                    $mysql->makeLimitSQL(0, $size, $query->params)
                ]);

                return $mysql->req($query)->reqToArrays();
            })
            ->setFnDo(function ($items) use (
                $elastic, $entity, $catalogPk, $index, $columnsOptions, $searchColumns, $mappingKeys, &$aff
            ) {
                $items = Arrays::mapByKeyValueMaker($items, function ($i, $item) use (
                    $catalogPk, $columnsOptions, $searchColumns, $mappingKeys
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

                    foreach ($searchColumns as $column) {
                        if (isset($item[$column])) {
                            $item[$column . '_length'] = mb_strlen($item[$column]);
                        }
                    }

                    if (isset($item['meta'])) {
                        if ($meta = json_decode($item['meta'], true)) {
                            $item['count'] = $meta['count'];
                        }

                        unset($item['meta']);
                    }

                    $id = $item[$catalogPk];
                    unset($item[$catalogPk]);

//                    $item2 = Arrays::filterByKeysArray($item, $mappingKeys);
//
//                    if ($item != $item2) {
//                        var_dump($item, $item2);
//                        die;
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
        $alias = $this->app->managers->catalog->getEntity()->getTable();
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