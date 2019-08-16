<?php

namespace SNOWGIRL_SHOP;

use SNOWGIRL_CORE\App;
use SNOWGIRL_SHOP\App\Web;
use SNOWGIRL_SHOP\App\Console;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Helper\Strings;
use SNOWGIRL_CORE\Helper\FS;
use SNOWGIRL_CORE\Service\Logger;
use SNOWGIRL_CORE\Script;
use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_SHOP\Entity\Import\History as ImportHistory;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Image;
use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttr;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;
use SNOWGIRL_SHOP\Entity\Country;
use SNOWGIRL_SHOP\Item\FixWhere;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;

/**
 * Class Import
 *
 * @property App|Web|Console app
 * @package SNOWGIRL_SHOP
 * @see     https://csv.thephpleague.com/9.0/
 */
class Import
{
    protected const LIMIT = 500;

    /** @var App|Web|Console */
    protected $app;

    /** @var ImportSource */
    protected $source;
    protected $indexes;
    protected $columns;

    //mva file columns sources (where to find mva names and terms names), db-column => file-column[]
    protected $sources = [];
    //which langs use to lookup in sources
    protected $langs = ['ru'];
    protected $filters = [];
    protected $mappings = [];

    protected $csvFileDelimiter = ';';

    protected $isCheckUpdatedAt;

    public function __construct(App $app, ImportSource $source)
    {
        $this->app = $app;

        $this->source = $source;

        $meta = $this->getMeta();
        $this->indexes = $meta['indexes'];
        $this->columns = $meta['columns'];

        $this->filters = $this->getFilters();
        $this->mappings = $this->getMappings();

        $this->initialize();
    }

    protected function initialize()
    {
    }

    protected function getFilters()
    {
        if ($this->filters && !$this->source->getFileFilter(true)) {
            $this->source->setFileFilter($this->filters);
        }

        return $this->source->getFileFilter(true);
    }

    protected function getMappings()
    {
        if ($this->mappings && !$this->source->getFileMapping(true)) {
            $this->source->setFileMapping($this->mappings);
        }

        return $this->source->getFileMapping(true);
    }

    public function getMeta()
    {
        $output = [
            'columns' => [],
            'indexes' => []
        ];

        if ($handler = fopen($this->getCsvFile(), 'r')) {
            $output['columns'] = explode($this->csvFileDelimiter, rtrim(fgets($handler)));
            fclose($handler);
        }

        $output['indexes'] = array_combine($output['columns'], range(0, count($output['columns']) - 1));

        return $output;
    }

    protected function setAccessPermissions($filename)
    {
        chgrp($filename, $this->app->config->server->web_server_group);
        chown($filename, $this->app->config->server->web_server_user);
        FS::chmodRecursive($filename, 0775);
        return $this;
    }

    protected function getCachePath()
    {
        $dir = implode('/', [
            $this->app->dirs['@tmp'],
            'import'
        ]);

        clearstatcache(true, $dir);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
            $this->setAccessPermissions($dir);
        }

        $dir .= '/' . md5($this->source->getFile());

        clearstatcache(true, $dir);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
            $this->setAccessPermissions($dir);
        }

        return $dir;
    }

    public function dropCache()
    {
        $this->log('flushing cache...');
        return FS::rmDir($this->getCachePath());
    }

    public function getDownloadedRawFileName()
    {
        return implode('/', [
            $this->getCachePath(),
            'src'
        ]);
    }

    public function getDownloadedCsvFileName()
    {
        return implode('/', [
            $this->getCachePath(),
            'src.csv'
        ]);
    }

    protected function getCsvFile()
    {
        clearstatcache(true, $file = $this->getDownloadedCsvFileName());

        if (!file_exists($file)) {
            clearstatcache(true, $file2 = $this->getDownloadedRawFileName());

            if (!file_exists($file2)) {
                $this->log('downloading file...');

                shell_exec(implode(' ', [
                    'wget --quiet',
                    '--output-document=' . $file2,
                    '"' . $this->source->getFile() . '"',
                    '> /dev/null'
                ]));

                $this->setAccessPermissions($file2);
            }

            $this->log('making csv...');
//                $this->makeCsv();
            rename($file2, $file);

            $this->setAccessPermissions($file);
        }

        if (!in_array(mime_content_type($file), [
            'text/csv',
            'text/plain',
            'application/csv',
            'text/comma-separated-values',
            'application/excel',
            'application/vnd.ms-excel',
            'application/vnd.msexcel',
            'text/anytext',
            'application/octet-stream',
            'application/txt'
        ])) {
            throw new Exception('invalid csv file "' . $file . '"');
        }

        return $file;
    }

    protected function readFileRow($handle, $delimiter)
    {
        //completed values
        $row = [];

        //non-completed parts from quotes - terminated by EON
        $quote = [];

        while (!feof($handle)) {
            $line = trim(fgets($handle));

            if ($quote) {
                if (false === strpos($line, '"')) {
                    $quote[] = $line;
                } else {
                    $expByQ = explode('"', $line);

                    $quote[] = array_shift($expByQ);

                    $row[] = implode("\n", $quote);
                    $quote = [];

                    $line = ltrim(implode('"', $expByQ), $delimiter);
                }
            }

            if (!$quote) {
                if (false === strpos($line, '"')) {
                    $row = array_merge($row, explode($delimiter, $line));
                } else {
                    $expByQ = explode('"', $line);

                    if (0 == count($expByQ) % 2) {
                        //we do have EON inside quotes here
                        $quote[] = array_pop($expByQ);
                    }

                    foreach ($expByQ as $k => $v) {
                        if (0 == $k % 2) {
                            $row = array_merge($row, explode($delimiter, trim($v, $delimiter)));
                        } else {
                            $row[] = $v;
                        }
                    }
                }
            }

            $s = count($row);

            if ($s == $this->walkFileSize) {
                //return
                break;
            } elseif ($s > $this->walkFileSize) {
                //skip current and continue with other row
                $row = [];
            }
        }

        return $row;
    }

    protected $walkFileSize;

    protected function walkFilteredFile(\Closure $fn, $allowModifyOnly = true, array $allowModifyOnlyExclude = [])
    {
        $this->walkFileSize = count($this->columns);

        $i = 0;

        if (!$handle = fopen($this->getCsvFile(), 'r')) {
            return false;
        }

        //skip first line (columns)
        fgets($handle);

        while ($row = static::readFileRow($handle, $this->csvFileDelimiter)) {
            if (!is_array($row)) {
                continue;
            }

            if (count($row) != $this->walkFileSize) {
                continue;
            }

            $row = $this->preNormalizeRow($row);

            $ok = true;

            foreach ($this->filters as $column => $f) {
                $okTmp2 = true;

                foreach (['equal' => false, 'not_equal' => true] as $k => $v) {
                    if (array_key_exists($k, $f) && $f[$k]) {
                        $okTmp = $v;

                        foreach ($f[$k] as $tmp2) {
                            foreach (explode(',', $tmp2) as $tmp22) {
                                if (false !== mb_stripos($row[$this->indexes[$column]], $tmp22)) {
                                    $okTmp = !$v;
                                    break;
                                }
                            }
                        }

                        if (!$okTmp) {
                            $okTmp2 = false;
                            break;
                        }
                    }
                }

                if (!$okTmp2) {
                    $ok = false;
                    break;
                }
            }

            if (!$ok) {
                continue;
            }

            if ($allowModifyOnly) {
                $ok = true;

                foreach ($this->mappings as $dbColumn => $vvv) {
                    if (
                        isset($vvv['modify'])
                        && !array_key_exists(trim($row[$this->indexes[$vvv['column']]]), $vvv['modify'])
                        && in_array('modify_only', $vvv)
                        && !in_array($vvv['column'], $allowModifyOnlyExclude)
                    ) {
                        $ok = false;
                        break;
                    }
                }

                if (!$ok) {
                    continue;
                }
            }

            $row = $this->postNormalizeRow($row);

            if (false === $fn($row, $i++)) {
                break;
            }
        }

        fclose($handle);

        return true;
    }

    protected function preNormalizeRow($row)
    {
        return $row;
    }

    protected function postNormalizeRow($row)
    {
        return $row;
    }

    public function getData(int $page = 1, int $size = 10): \stdClass
    {
        $return = new \stdClass();

        $return->columns = $this->columns;
        $return->indexes = $this->indexes;

        $tmp = [];
        $total = 0;
        $startIndex = ($page - 1) * $size;
        $endIndex = $page * $size - 1;

        $this->walkFilteredFile(function ($row, $i) use ($startIndex, $endIndex, &$tmp, &$total) {
            $total++;

            if ($i < $startIndex || $i > $endIndex) {
                return true;
            }

            $tmp[] = $row;
            return true;
        });

        $return->data = $tmp;
        $return->totalItems = $total;
        $return->totalPages = ceil($return->totalItems / $size);

        return $return;
    }

    public function getFileColumnsValuesInfo(array $columns, bool $allowModifyOnly = true, array $allowModifyOnlyExclude = [])
    {
        $output = array_combine($columns, array_fill(0, count($columns), []));

        $this->walkFilteredFile(function ($row) use ($columns, &$output) {
            foreach ($columns as $column) {
                $v = $row[$this->indexes[$column]];

                if (!isset($output[$column][$v])) {
                    $output[$column][$v] = [
                        'total' => 0,
                        'items' => []
                    ];
                }

                $output[$column][$v]['total']++;

                if ($output[$column][$v]['total'] < 6) {
                    $output[$column][$v]['items'][] = $row;
                }
            }
        }, $allowModifyOnly, $allowModifyOnlyExclude);

        if (isset($this->mappings['image'])) {
            if (isset($this->indexes[$this->mappings['image']['column']])) {
                $imageIndex = $this->indexes[$this->mappings['image']['column']];
            }
        } else {
            foreach (['image', 'picture'] as $possibleKey) {
                if (in_array($possibleKey, $this->columns)) {
                    $imageIndex = $this->indexes[$possibleKey];
                }
            }
        }

        if (isset($this->mappings['name'])) {
            if (isset($this->indexes[$this->mappings['name']['column']])) {
                $nameIndex = $this->indexes[$this->mappings['name']['column']];
            }
        } else {
            foreach (['entity', 'name'] as $possibleKey) {
                if (in_array($possibleKey, $this->columns)) {
                    $nameIndex = $this->indexes[$possibleKey];
                }
            }
        }

        foreach ($output as $fileColumn => $fileColumnValueInfo) {
            foreach ($fileColumnValueInfo as $value => $info) {
                foreach ($output[$fileColumn][$value]['items'] as $k => $item) {
                    $output[$fileColumn][$value]['items'][$k] = [
                        'image' => isset($imageIndex) && isset($item[$imageIndex]) ? str_replace(['http://', 'https://'], '//', $item[$imageIndex]) : null,
                        'name' => isset($nameIndex) && isset($item[$nameIndex]) ? $item[$nameIndex] : null
                    ];
                }
            }
        }

        return $output;
    }

    public function getMappingFileColumnsValuesInfo(&$counts)
    {
        $columns = [];

        foreach ($this->mappings as $dbColumn => $v) {
            if (isset($v['modify']) && count($v['modify'])) {
                $columns[] = $v['column'];
            }
        }

        $output = $this->getFileColumnsValuesInfo($columns, true);

        $counts = 0;

        foreach ($output as $fileColumn => $info) {
            foreach ($info as $fileColumnValue => $itemsInfo) {
                $counts += isset($itemsInfo['items']) && count($itemsInfo['items']) > 0 ? 1 : 0;
            }
        }

        return $output;
    }

    public function getFileColumnValuesInfo($column)
    {
        return $this->getFileColumnsValuesInfo([$column], true, [$column])[$column];
    }

    public function getItemColumns()
    {
        $output = array_diff(array_keys($this->app->managers->items->getEntity()->getColumns()), [
            $this->app->managers->items->getEntity()->getPk(),
            $this->app->managers->sources->getEntity()->getPk(),
            $this->app->managers->vendors->getEntity()->getPk(),
            'image_count',
            'rating',
            'is_sport',
            'is_size_plus',
            'order_desc_rating',
            'order_asc_price',
            'order_desc_price',
            'created_at'
        ]);

        if (ImportSource::TYPE_PARTNER != $this->source->getType()) {
            $output = array_diff($output, ['partner_link']);
        }

        return $output;
    }

    public function getRequiredItemColumns()
    {
        $output = array_diff($this->getItemColumns(), [
            $this->app->managers->countries->getEntity()->getPk(),
            'old_price',
            'description',
            'entity',
            $this->app->managers->sources->getEntity()->getPk()
        ]);

        if (ImportSource::TYPE_PARTNER != $this->source->getType()) {
            $output = array_diff($output, ['partner_link']);
        }

        return $output;
    }

    public static function getSvaValues(App $app)
    {
        return [
            Category::getPk() => $app->utils->attrs->getIdToName(Category::class),
//            Brand::getPk() => $app->utils->attrs->getIdToName(Brand::class),
            Country::getPk() => $app->utils->attrs->getIdToName(Country::class)
        ];
    }

    protected function before()
    {

    }

    protected function setOutOfStock()
    {
        $this->app->managers->items->updateMany(['is_in_stock' => 0], [
            'import_source_id' => $this->source->getId()
        ]);

        return $this;
    }

    protected $values;
    protected $itemColumns;
    protected $requiredColumns;
    protected $isRuLang;
    protected $index;
    protected $bindValues;
    protected $passed;
    protected $skippedByOther;
    protected $skippedByUniqueKey;
    protected $skippedByUpdatedAt;
    protected $startIndex;
    protected $endIndex;
    protected $defaultAllowModifyOnly = true;
    protected $partnerType;

    protected $sport;
    protected $sizePlus;
    protected $tags;

    protected $microtime;

    protected function getPartnerUpdatedAtByPartnerItemId(array $partnerItemId)
    {
        $output = [];

        $tmp = $this->app->managers->items->clear()
            ->setColumns(['partner_item_id', 'partner_updated_at'])
            ->setWhere(['import_source_id' => $this->source->getId(), 'partner_item_id' => $partnerItemId])
            ->setQueryParam('log', false)
            ->getArrays();

        foreach ($tmp as $v) {
            $output[$v['partner_item_id']] = $v['partner_updated_at'];
        }

        return $output;
    }

    protected function getPartnerItemIdByImage(array $image)
    {
        $output = [];

        $tmp = $this->app->managers->items->clear()
            ->setColumns(['partner_item_id', 'image'])
            ->setWhere(['import_source_id' => $this->source->getId(), 'image' => $image])
            ->setQueryParam('log', false)
            ->getArrays();

        foreach ($tmp as $v) {
            $output[$v['image']] = $v['partner_item_id'];
        }

        return $output;
    }

    protected $svaComponents;
    protected $sva;

    protected function prepareSva()
    {
        $this->svaComponents = $this->app->managers->catalog->getSvaComponents();
        $this->sva = [];

        foreach ($this->svaComponents as $entityClass) {
            /** @var string|Entity $entityClass */
            /** @var ItemAttrManager $manager */
            $manager = $this->app->managers->getByEntityClass($entityClass);

            $entity = $manager->getEntity();
            $table = $entity->getTable();

            list($nameToId, $uriToId) = $this->app->utils->attrs->getNameToIdAndUriToId($manager->copy(true), true);

            if ($termsManager = $manager->getTermsManager()) {
                $where = isset($termsManager->getEntity()->getColumns()['lang']) ? ['lang' => $this->langs] : null;
                $termNameToId = $this->app->utils->attrs->getTermNameToAttrId($termsManager->copy(true)->setWhere($where));
            } else {
                $termNameToId = [];
            }

            $this->sva[$entity->getPk()] = [
                'entity' => $entity,
                'manager' => $manager,
                'method' => 'get' . Strings::underscoreToCamelCase($table, false) . 'ByRow',
                'nameToId' => $nameToId,
                'termNameToId' => $termNameToId,
                'uriToId' => $uriToId,
                'processNew' => !!$this->app->config->import->$table(false)
            ];
        }
    }

    protected function retrieveSva($row)
    {
        $output = [];

        foreach ($this->sva as $pk => &$data) {
            /** @var Entity[]|Manager[] $data */

            if ($rawNameOrId = $this->{$data['method']}($row)) {
                $id = 0;

                if (is_int($rawNameOrId)) {
                    $id = $rawNameOrId;
                } else {
                    $rawNameOrId = trim($rawNameOrId);
                    $nameOrId = $data['entity']->normalizeText($rawNameOrId);

                    if (isset($data['nameToId'][$nameOrIdLower = mb_strtolower($nameOrId)])) {
                        $id = $data['nameToId'][$nameOrIdLower];
                    } elseif (isset($data['uriToId'][$uri = $data['entity']->normalizeUri($nameOrId)])) {
                        $id = $data['uriToId'][$uri];
                        $data['nameToId'][$nameOrIdLower] = $id;
                    } elseif (isset($data['uriToId'][$uriTable = $uri . '-' . $data['entity']->getTable()])) {
                        $id = $data['uriToId'][$uriTable];
                        $data['nameToId'][$nameOrIdLower] = $id;
                    } else {
                        try {
                            foreach ($this->sva as $data2) {
                                if (isset($data2['uriToId'][$uri])) {
                                    $uri = $uriTable;
                                    break;
                                }
                            }

                            /** @var ItemAttr $entity */
                            $entity = clone $data['entity'];

                            $id = $data['manager']->insertOne($entity->setRawAttr('name', $nameOrId)->setRawAttr('uri', $uri));

                            $data['nameToId'][$nameOrIdLower] = $id;
                            $data['uriToId'][$uri] = $id;
                        } catch (\Exception $ex) {
                            $this->app->services->logger->makeException($ex);
                            $this->log('can\'t insert entity: ' . var_export($entity, true));
                        }
                    }
                }

                $output[$pk] = (int)$id;
            } else {
                $output[$pk] = null;
            }
        }

        return $output;
    }

    /**
     * Returns mixed values (names or/and IDs)
     *
     * @param $pk
     * @param $row
     *
     * @return int
     */
    protected function getSvaByRow($pk, $row)
    {
        if (isset($this->mappings[$pk])) {
            $m = $this->mappings[$pk];

            if (isset($m['value']) && $m['value']) {
                return (int)$m['value'];
            } elseif (isset($m['column'])) {
                $c = $m['column'];

                if (isset($this->indexes[$c])) {
                    $from = trim($row[$this->indexes[$c]]);

                    if (array_key_exists('modify', $m) && array_key_exists($from, $modifies = $m['modify']) && $modifies[$from]['value']) {
                        return (int)$modifies[$from]['value'];
                    }

                    if ($this->sva[$pk]['processNew']) {
                        return $from;
                    }
                }
            }
        }

        if (isset($this->sources[$pk])) {
            foreach ((array)$this->sources[$pk] as $c) {
                if (isset($this->indexes[$c])) {
                    $i = $this->indexes[$c];

                    if ($source = trim($row[$i])) {
                        if ($this->isRuLang) {
                            foreach ($this->sva[$pk]['nameToId'] as $value => $id) {
                                if (false !== mb_stripos($source, $value)) {
                                    return (int)$id;
                                }
                            }
                        }

                        foreach ($this->sva[$pk]['termNameToId'] as $term => $id) {
                            if (false !== mb_stripos($source, $term)) {
                                return (int)$id;
                            }
                        }
                    }
                }
            }
        }

        return 0;
    }

    protected function getCategoryByRow($row)
    {
        $pk = 'category_id';
        $map = $this->mappings[$pk];

        if (isset($map['column'])) {
            $value = trim($row[$this->indexes[$map['column']]]);

            if (array_key_exists('modify', $map) && array_key_exists($value, $modifies = $map['modify']) && $modifies[$value]['value']) {
                $this->tags = array_merge($this->tags, $modifies[$value]['tags']);
                $this->sport = $this->sport || in_array('is_sport', $modifies[$value]);
                $this->sizePlus = $this->sizePlus || in_array('is_size_plus', $modifies[$value]);
                return (int)$modifies[$value]['value'];
            }

            return $this->getSvaByRow($pk, $row);
        } elseif ($map['value']) {
            return (int)$map['value'];
        }

        return null;
    }

    protected function getBrandByRow($row)
    {
        return $this->getSvaByRow('brand_id', $row);
    }

    protected function getCountryByRow($row)
    {
        return $this->getSvaByRow('country_id', $row);
    }

    protected function getVendorByRow($row)
    {
        return $this->source->getVendorId();
    }

    protected function getImportSourceByRow($row)
    {
        return $this->source->getId();
    }

    protected $mvaComponents;
    protected $mva;

    protected function prepareMva()
    {
        $this->mvaComponents = $this->app->managers->catalog->getMvaComponents();
        $this->mva = [];

        foreach ($this->mvaComponents as $entityClass) {
            /** @var string|Entity $entityClass */
            /** @var ItemAttrManager $manager */
            $manager = $this->app->managers->getByEntityClass($entityClass);

            $entity = $manager->getEntity();
            $table = $entity->getTable();

            list($nameToId, $uriToId) = $this->app->utils->attrs->getNameToIdAndUriToId($manager->copy(true), true);

            if ($termsManager = $manager->getTermsManager()) {
                $where = isset($termsManager->getEntity()->getColumns()['lang']) ? ['lang' => $this->langs] : null;
                $termNameToId = $this->app->utils->attrs->getTermNameToAttrId($termsManager->copy(true)->setWhere($where));
            } else {
                $termNameToId = [];
            }

            $this->mva[$entity->getPk()] = [
                'entity' => $entity,
                'manager' => $manager,
                'method' => 'get' . Strings::underscoreToCamelCase($entity->getTable()) . 'sByRow',
                'nameToId' => $nameToId,
                'termNameToId' => $termNameToId,
                'uriToId' => $uriToId,
                'imageToArray' => [],
                'processNew' => !!$this->app->config->import->$table(false)
            ];
        }
    }

    protected function retrieveMva($row, $image)
    {
        foreach ($this->mva as $pk => $data) {
            if ($tmp = $this->{$data['method']}($row)) {
                if (!isset($this->mva[$pk]['imageToArray'][$image])) {
                    $this->mva[$pk]['imageToArray'][$image] = [];
                }

                $this->mva[$pk]['imageToArray'][$image] = array_merge($this->mva[$pk]['imageToArray'][$image], $tmp);
            }
        }
    }

    protected function insertMva()
    {
        $itemPk = $this->app->managers->items->getEntity()->getPk();

        foreach ($this->mvaComponents as $entityClass) {
            try {
                /** @var string|Entity $entityClass */

                /** @var ItemAttrManager $manager */
                $manager = $this->app->managers->getByEntityClass($entityClass);
                /** @var ItemAttr $entity */
                $entity = $manager->getEntity();

                $entityPk = $entity->getPk();

                $data = $this->mva[$entityPk];

                if (count($data)) {
                    $entityTable = $entity->getTable();

                    $insert = [];

                    $imageToId = $this->app->managers->items->getImageToId(['image' => array_keys($data['imageToArray'])]);

                    foreach ($data['imageToArray'] as $image => $rawArray) {
                        if (!isset($imageToId[$image])) {
                            $this->log('$imageToId[' . $image . '] not found... skipping...');
                            continue;
                        }

                        $rawArray = array_unique($rawArray, SORT_REGULAR);

                        foreach ($rawArray as $rawNameOrId) {
                            $id = null;

                            if (is_int($rawNameOrId)) {
                                $id = $rawNameOrId;
                            } else {
                                $rawNameOrId = trim($rawNameOrId);
                                $nameOrId = mb_strtolower($rawNameOrId);

                                if (isset($data['nameToId'][$nameOrId])) {
                                    $id = $data['nameToId'][$nameOrId];
                                } elseif (isset($data['uriToId'][$newUri = $entity->normalizeUri($nameOrId)])) {
                                    $id = $data['uriToId'][$newUri];
                                    $this->mva[$entityPk]['nameToId'][$nameOrId] = $id;
                                } else {
                                    try {
                                        while (true) {
                                            foreach ($this->sva as $data2) {
                                                if (isset($data2['nameToId'][$nameOrId])) {
                                                    break 2;
                                                }
                                            }

                                            foreach ($this->mva as $data2) {
                                                if (isset($data2['nameToId'][$nameOrId])) {
                                                    break 2;
                                                }
                                            }

                                            /** @var ItemAttr $object */
                                            $object = new $entityClass(['name' => $rawNameOrId]);
                                            $id = $manager->insertOne($object);
                                            $this->mva[$entityPk]['uriToId'][$object->getUri()] = $id;
                                            $this->mva[$entityPk]['nameToId'][$nameOrId] = $id;

                                            break;
                                        }
                                    } catch (\Exception $ex) {
                                        $this->app->services->logger->makeException($ex);

                                        if (Exception::_check($ex, 'Duplicate entry')) {
                                            $tmp = $this->app->storage->mysql->selectOne($entityTable, new Query([
                                                'columns' => $entityPk,
                                                'where' => ['uri' => $newUri]
                                            ]));

                                            if ($tmp) {
                                                $id = $tmp[$entityPk];
                                                $this->mva[$entityPk]['uriToId'][$newUri] = $id;
                                                $this->mva[$entityPk]['nameToId'][$nameOrId] = $id;
                                            } else {
                                                $this->log('Can\'t figure out ' . $entityTable . ' id in case of duplicate');
                                            }
                                        } else {
                                            $this->log('Can\'t figure out ' . $entityTable . ' id');
                                        }
                                    }
                                }
                            }

                            if ($id) {
                                $insert[] = [
                                    $itemPk => (int)$imageToId[$image],
                                    $entityPk => (int)$id
                                ];
                            }
                        }
                    }

                    $aff = $insert ? $manager->getMvaLinkManager()->insertMany($insert, ['ignore' => true, 'log' => true]) : 0;
                } else {
                    $aff = 0;
                }

                $this->log('AFF ' . $manager->getMvaLinkManager()->getEntity()->getTable() . ': ' . $aff);
            } catch (\Exception $ex) {
                $this->log('Error on mva[' . $manager->getMvaLinkManager()->getEntity()->getTable() . '] insert: ' . $ex->getMessage());
                $this->app->services->logger->makeException($ex);
            }
        }
    }

    /**
     * Returns IDs
     *
     * @param $pk
     * @param $row
     *
     * @return array
     */
    protected function getMvaByRow($pk, $row)
    {
        $output = [];

        if (isset($this->sources[$pk])) {
            foreach ((array)$this->sources[$pk] as $c) {
                if (isset($this->indexes[$c])) {
                    if ($source = trim($row[$this->indexes[$c]])) {
                        if ($this->isRuLang) {
                            foreach ($this->mva[$pk]['nameToId'] as $value => $id) {
                                if (false !== mb_stripos($source, $value)) {
                                    $output[] = $id;
                                }
                            }
                        }

                        if (0 == count($output)) {
                            foreach ($this->mva[$pk]['termNameToId'] as $term => $id) {
                                if (false !== mb_stripos($source, $term)) {
                                    $output[] = $id;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $output;
    }

    protected function getColorsByRow($row)
    {
        return $this->getMvaByRow('color_id', $row);
    }

    protected function getSizesByRow($row)
    {
        return $this->getMvaByRow('size_id', $row);
    }

    protected function getMaterialsByRow($row)
    {
        return $this->getMvaByRow('material_id', $row);
    }

    protected function getSeasonsByRow($row)
    {
        return $this->getMvaByRow('season_id', $row);
    }

    protected function getTagsByRow($row)
    {
        return $this->getMvaByRow('tag_id', $row);
    }

    protected function getNameByRow($row)
    {
        return trim($row[$this->indexes[$this->mappings['name']['column']]]);
    }

    protected function getImageNoCheckNoDownloadByRow($row, &$count = 1)
    {
        $images = array_map('trim', explode(',', $row[$this->indexes[$this->mappings['image']['column']]]));

        $first = array_shift($images);

        $image = $this->app->images->getHash($first);

        $count += count($images);

        return $image;
    }

    protected function getImageByRow($row, &$count = 1)
    {
        $images = array_map('trim', explode(',', $row[$this->indexes[$this->mappings['image']['column']]]));

        $first = array_shift($images);

        if (!$first) {
            $this->log('empty image', Logger::TYPE_ERROR);
            return false;
        }

        $error = null;

        if (!$image = $this->app->images->downloadWithWget($first, null, $error)) {
            $this->log($first . ': ' . $error, Logger::TYPE_ERROR);
            return false;
        }

        if (0 < count($images)) {
            $part = substr($image, 0, strlen($image) - 1);

            foreach ($images as $im) {
                $newImage = $part . $count;

                if ($newImage == $image) {
                    $newImage = $part . ($count + 1);
                }

                if ($this->app->images->downloadWithWget($im, $newImage, $error)) {
                    $count++;
                } else {
                    $this->log($im . ': ' . $error, Logger::TYPE_ERROR);
                }
            }
        }

        return $image;
    }

    protected function getPriceByRow($row)
    {
        $v = (float)trim($row[$this->indexes[$this->mappings['price']['column']]]);

        if ($v > 999999.99) {
            return false;
        }

        return number_format($v, 2, '.', '');
    }

    /**
     * @todo use Item Entity's Columns info...
     *
     * @param $row
     *
     * @return float|null
     */
    protected function getOldPriceByRow($row)
    {
        if (isset($this->mappings['old_price']) && $v = (float)trim($row[$this->indexes[$this->mappings['old_price']['column']]])) {
            if ($v > 999999.99) {
                return null;
            }

            return number_format($v, 2, '.', '');
        }

        return null;
    }

    protected function getPartnerItemIdByRow($row)
    {
        return trim($row[$this->indexes[$this->mappings['partner_item_id']['column']]]);
    }

    protected function getIsInStockByRow($row)
    {
        $map = $this->mappings['is_in_stock'];
        $value = trim($row[$this->indexes[$map['column']]]);

        if (array_key_exists('modify', $map) && array_key_exists($value, $modifies = $map['modify'])) {
            return (int)$modifies[$value]['value'];
        }

        return 1;
    }

    protected function getPartnerLinkByRow($row)
    {
        return trim($row[$this->indexes[$this->mappings['partner_link']['column']]]);
    }

    protected function getEntityByRow($row)
    {
        if (isset($this->mappings['entity'])) {
            $map = $this->mappings['entity'];
            $value = trim($row[$this->indexes[$this->mappings['entity']['column']]]);

            if (array_key_exists('modify', $map) && array_key_exists($value, $modifies = $map['modify']) && strlen($modifies[$value]['value'])) {
                $this->tags = array_merge($this->tags, $modifies[$value]['tags']);
                $this->sport = $this->sport || in_array('is_sport', $modifies[$value]);
                $this->sizePlus = $this->sizePlus || in_array('is_size_plus', $modifies[$value]);
                $value = $modifies[$value]['value'];
            }

            return $value;
        }

        return '';
    }

    protected function getPartnerUpdatedAtByRow($row)
    {
        return isset($row[$this->indexes[$this->mappings['partner_updated_at']['column']]])
            ? (int)trim($row[$this->indexes[$this->mappings['partner_updated_at']['column']]])
            : $this->microtime;
    }

    protected function _insertItems()
    {
        $this->log('-----------PROGRESS-----------')
            ->log('PASSED = ' . $this->passed)
            ->log('SKIPPED by unique key = ' . $this->skippedByUniqueKey)
            ->log('SKIPPED by updated at = ' . $this->skippedByUpdatedAt)
            ->log('SKIPPED by other = ' . $this->skippedByOther)
            ->log('SKIPPED = ' . ($this->skippedByOther + $this->skippedByUniqueKey + $this->skippedByUpdatedAt))
            ->log('------------------------------');

        if ((!$this->values) || (!$this->bindValues) || (!$this->index)) {
            return false;
        }

        $columnsOptions = $this->app->managers->items->getEntity()->getColumns();

        $tmp = [];

        for ($i = 0, $s = count($this->values); $i < $this->index; $i++) {
            $tmp[] = implode(', ', array_fill(0, $s, '?'));
        }

        $onDuplicateIgnoreColumns = [
            $this->app->managers->items->getEntity()->getPk(),
            $this->app->managers->vendors->getEntity()->getPk(),
            $this->app->managers->sources->getEntity()->getPk(),
            'image',
            'partner_item_id',
            'created_at'
        ];

        $editableColumns = self::getPostEditableColumns();

        $db = $this->app->storage->mysql;

        $onDuplicateClosure = function ($column, $value) use ($db) {
            return $db->quote($column) . ' = IF(' . implode(', ', [
                    $db->quote('image') . ' = VALUES(' . $db->quote('image') . ')',
                    //if duplicate on image - then
                    'IF(' . implode(', ', [
                        $db->quote('partner_item_id') . ' = VALUES(' . $db->quote('partner_item_id') . ')',
                        //update
                        $value,
                        //ignore (do nothing)
                        $db->quote($column)
                    ]) . ')',
                    //update
                    $value
                ]) . ')';
        };

        $query = new Query;
        $query->text = implode(' ', [
            'INSERT INTO',
            $db->quote($this->app->managers->items->getEntity()->getTable()),
            '(' . implode(', ', array_map(function ($i) use ($db) {
                return $db->quote($i);
            }, array_keys($this->values))) . ') VALUES ' . implode(', ', array_map(function ($i) {
                return '(' . $i . ')';
            }, $tmp)),
            'ON DUPLICATE KEY UPDATE',

            //@todo next code overwrites all items fixes... (+add fixWhere clauses maybe?! +take into account "updated_at" in this query)

            implode(', ', array_map(function ($column) use ($onDuplicateClosure, $db) {
                return $onDuplicateClosure($column, 'VALUES(' . $db->quote($column) . ')');
            }, array_diff(array_keys($this->values), $onDuplicateIgnoreColumns, $editableColumns))) . ' ,',
            implode(', ', array_map(function ($column) use ($onDuplicateClosure, $db, $columnsOptions) {
                $options = $columnsOptions[$column];

                return $onDuplicateClosure($column, 'IF(' . implode(', ', [
                        implode(' OR ', array_filter([
                            !in_array(Entity::REQUIRED, $options) ? ($db->quote($column) . ' IS NULL') : null,
                            Entity::COLUMN_INT == $options['type'] ? ($db->quote($column) . ' = 0') : null,
                            Entity::COLUMN_TEXT == $options['type'] ? ($db->quote($column) . ' = \'\'') : null
                        ], function ($v) {
                            return null !== $v;
                        })),
                        'VALUES(' . $db->quote($column) . ')',
                        $db->quote($column)
                    ]) . ')');
            }, $editableColumns))
        ]);
        $query->params = $this->bindValues;
        $query->log = false;

        return $db->req($query)->affectedRows();
    }

    protected function insertItems()
    {
        try {
            $aff = $this->_insertItems();
            $this->log('AFF item: ' . (int)$aff);
        } catch (\Exception $ex) {
            $this->app->services->logger->makeException($ex);
        }

        return $this;
    }

    public static function factoryAndRun(App $app, ImportSource $importSource = null, $safe = true)
    {
        /** @var ImportSource[] $importSources */
        $importSources = $importSource ? [$importSource] : $app->managers->sources->clear()
            ->setWhere($safe ? ['is_cron' => 1] : null)
            ->setOrders([ImportSource::getPk() => SORT_ASC])
            ->getObjects();

        if (!count($importSources)) {
            return true;
        }

        foreach ($importSources as $importSource) {
            try {
                if (!$safe || $app->managers->sources->getVendor($importSource)->isActive()) {
                    $app->managers->sources->getImport($importSource)->run();
                } else {
                    $app->services->logger->make('Vendor "' . $app->managers->sources->getVendor($importSource)->getName() . '" is disabled');
                }
            } catch (\Exception $ex) {
                $app->services->logger->makeException($ex);
                $app->services->logger->make('Import (import source id = ' . $importSource->getId() . ') failed!');
            }
        }

        return true;
    }

    protected function getLastHistory(): ?ImportHistory
    {
        return $this->app->managers->importHistory
            ->setWhere([ImportSource::getPk() => $this->source->getId()])
            ->setOrders([ImportHistory::getPk() => SORT_DESC])
            ->getObject();
    }

    protected function getUniqueFileHash()
    {
        $file = new Script($this->getCsvFile());

        $tmp = $file->getUniqueHash();
        $tmp = md5($tmp . serialize($this->source->getFileFilter()));
        $tmp = md5($tmp . serialize($this->source->getFileMapping()));

        return $tmp;
    }

    /**
     * @var ImportHistory
     */
    protected $lastHistory;

    protected function isNeed(&$uniqueFileHash = null, &$whyNoReason = null): bool
    {
        if ($this->lastHistory = $this->getLastHistory()) {
            if ($this->lastHistory->isOk() || (time() - strtotime($this->source->getCreatedAt()) >= 7 * 24 * 60 * 60)) {
                if (!$this->app->isDev()) {
                    $this->dropCache();
                }

                $uniqueFileHash = $this->getUniqueFileHash();

                if ($uniqueFileHash != $this->lastHistory->getFileUniqueHash()) {
                    return true;
                }

                $whyNoReason = 'File hashes are identical';
                return false;
            }

            $whyNoReason = 'Last import not finished';
            return false;
        }

        $uniqueFileHash = $this->getUniqueFileHash();
        return true;
    }

    /**
     * @var ImportHistory
     */
    protected $history;

    protected function createHistory(string $fileUniqueHash)
    {
        $this->history = (new ImportHistory)
            ->setImportSourceId($this->source->getId())
            ->setFileUniqueHash($fileUniqueHash);

        $this->app->managers->importHistory->insertOne($this->history);

        $this->log('History id: ' . $this->history->getId());

        return $this;
    }

    protected function updateHistory(bool $isOk)
    {
        if ($this->history && $this->history->getId()) {
            $this->history->setIsOk($isOk);
            $this->app->managers->importHistory->updateOne($this->history);
        } else {
            $this->log('History can\'t being updated');
        }

        return $this;
    }

    /**
     * @todo check & fix:
     *       1) source + id - MAIN(!) unique pair (not image)
     *       2) image should belongs to (source + id) item (as all rest fields)
     *       3) if item exists - do not check image (file_exists($hash)) - compare hashes only, and if not equal -
     *       update hash & download
     *
     * @param int $offset
     * @param int $length
     *
     * @return bool
     */
    protected function import(int $offset = 0, int $length = 9999999): bool
    {
        try {
            $this->before();

            $this->itemColumns = $this->getItemColumns();
            $this->requiredColumns = $this->getRequiredItemColumns();

            $this->isRuLang = in_array('ru', $this->langs);

            $this->prepareSva();
            $this->prepareMva();

            $this->index = 0;
            $this->bindValues = [];
            $this->passed = 0;
            $this->skippedByUniqueKey = 0;
            $this->skippedByUpdatedAt = 0;
            $this->skippedByOther = 0;
            $this->startIndex = $offset;
            $this->endIndex = $offset + $length - 1;

            $this->partnerType = ImportSource::TYPE_PARTNER == $this->source->getType();
            $this->isCheckUpdatedAt = $this->isCheckUpdatedAt && isset($this->mappings['partner_updated_at']['column']);

            $rows = [];

            $this->walkFilteredFile(function ($row, $k) use (&$rows) {
                if ($k < $this->startIndex) {
                    return true;
                }

                if ($k > $this->endIndex) {
                    return false;
                }

                $rows[] = $row;

                return true;
            }, $this->defaultAllowModifyOnly);

            foreach (array_chunk($rows, self::LIMIT) as $rows) {
                $partnerItemId = [];
                $image = [];

                foreach ($rows as $k => $row) {
                    //@todo validate(filter)
                    $tmp = $this->getPartnerItemIdByRow($row);
                    $partnerItemId[] = $tmp;
                    $rows[$k]['_partner_item_id'] = $tmp;
                    $imageCount = null;
                    $tmp = $this->getImageNoCheckNoDownloadByRow($row, $imageCount);
                    $image[] = $tmp;
                    $rows[$k]['_image'] = $tmp;
                    $rows[$k]['_image_count'] = $imageCount;
                }

                if ($this->isCheckUpdatedAt) {
                    $partnerItemIdToPartnerUpdatedAt = $this->getPartnerUpdatedAtByPartnerItemId($partnerItemId);
                } else {
                    $partnerItemIdToPartnerUpdatedAt = [];
                }

                $imageToPartnerItemId = $this->getPartnerItemIdByImage($image);

                foreach ($rows as $row) {
                    if (isset($imageToPartnerItemId[$row['_image']])) {
                        if ($imageToPartnerItemId[$row['_image']] != $row['_partner_item_id']) {
                            $this->retrieveMva($row, $row['_image']);
                            $this->log('[SKIPPED unique key] partner_id=' . $row['_partner_item_id'] . ' image=' . $row['_image']);
                            $this->skippedByUniqueKey++;
                            continue;
                        }
                    } else {
                        // force download
                        unset($row['_image'], $row['_image_count']);

                    }

                    if (isset($partnerItemIdToPartnerUpdatedAt[$row['_partner_item_id']])) {
                        $row['_partner_updated_at'] = $this->getPartnerUpdatedAtByRow($row);

                        if ($partnerItemIdToPartnerUpdatedAt[$row['_partner_item_id']] <= $row['_partner_updated_at']) {
                            $this->log('[SKIPPED updated_at] partner_id=' . $row['_partner_item_id'] . ' updated_at=' . $row['_partner_updated_at']);
                            $this->skippedByUpdatedAt++;
                            continue;
                        }
                    }

                    if (!$this->importRow($row)) {
                        break 2;
                    }
                }
            }

            if ($this->index) {
                $this->insertItems();
            }

            $this->insertMva();

            return true;
        } catch (\Exception $ex) {
            $this->app->services->logger->makeException($ex);
            return false;
        }
    }

    /**
     * @param      $row
     *
     * @return bool
     */
    protected function importRow($row)
    {
        $this->sport = null;
        $this->sizePlus = null;
        $this->tags = [];

        $ok = false;

        $values = [];

        while (true) {
            if (!$values['partner_item_id'] = array_key_exists('_partner_item_id', $row) ? $row['_partner_item_id'] : $this->getPartnerItemIdByRow($row)) {
                break;
            }

            $values = array_merge($values, $this->retrieveSva($row));

            if (!$values['category_id'] || !$values['brand_id']) {
                $this->log('[SKIPPED category or brand] partner_id=' . $values['partner_item_id']);
                break;
            }

            if (!$values['name'] = $this->getNameByRow($row)) {
                $this->log('[SKIPPED name] partner_id=' . $values['partner_item_id']);
                break;
            }

            if (!$values['price'] = $this->getPriceByRow($row)) {
                $this->log('[SKIPPED price] partner_id=' . $values['partner_item_id']);
                break;
            }

            if ($this->partnerType && !$values['partner_link'] = $this->getPartnerLinkByRow($row)) {
                $this->log('[SKIPPED link] partner_id=' . $values['partner_item_id']);
                break;
            }

            $imageCount = null;

            if (!$values['image'] = array_key_exists('_image', $row) ? $row['_image'] : $this->getImageByRow($row, $imageCount)) {
                $this->log('[SKIPPED image] partner_id=' . $values['partner_item_id']);
                break;
            }

            $values['image_count'] = array_key_exists('_image_count', $row) ? $row['_image_count'] : $imageCount;

            $values['import_source_id'] = $this->getImportSourceByRow($row);
            $values['old_price'] = $this->getOldPriceByRow($row);
            $values['is_in_stock'] = $this->getIsInStockByRow($row);
            $values['entity'] = $this->getEntityByRow($row);
            $values['is_sport'] = $this->sport ? 1 : 0;
            $values['is_size_plus'] = $this->sizePlus ? 1 : 0;
            $values['partner_updated_at'] = array_key_exists('_partner_updated_at', $row) ? $row['_partner_updated_at'] : $this->getPartnerUpdatedAtByRow($row);

            foreach (array_diff($this->itemColumns, array_keys($values)) as $dbColumn) {
                if (isset($this->mappings[$dbColumn])) {
                    $map = $this->mappings[$dbColumn];

                    $value = trim($row[$this->indexes[$map['column']]]);

                    if (array_key_exists('modify', $map) && array_key_exists($value, $modifies = $map['modify']) && null !== $modifies[$value]['value']) {
                        $value = $modifies[$value]['value'];
                    }

                    $values[$dbColumn] = $value;
                }
            }

            foreach ($this->requiredColumns as $dbColumn) {
                if (isset($values[$dbColumn]) && !mb_strlen($values[$dbColumn])) {
                    $this->log($dbColumn . '\' value is empty[' . var_export($values[$dbColumn], true) . '] ...ignoring record');
                    $this->log('[SKIPPED required ' . $dbColumn . '] partner_id=' . $values['partner_item_id']);
                    break;
                }
            }

            if ($this->tags) {
                $this->mva['tag_id']['imageToArray'][$values['image']] = array_unique($this->tags);
            }

            $this->retrieveMva($row, $values['image']);

            $ok = true;

            break;
        }

        if ($ok) {
            $this->log('[PASSED] partner_id=' . $values['partner_item_id']);

            $this->values = $values;

            foreach ($values as $v) {
                $this->bindValues[] = $v;
            }

            $this->passed++;
            $this->index++;
        } else {
            $this->skippedByOther++;
        }

        if (self::LIMIT == $this->index) {
            $this->insertItems();
            $this->index = 0;
            $this->bindValues = [];

            if ($this->app->isDev()) {
                return false;
            }
        }

        return true;
    }

    public function run(int $offset = 0, int $length = 9999999)
    {
        if (!$this->app->managers->sources->getVendor($this->source)->isActive()) {
            throw new \Exception('vendor [import_source_id=' . $this->source->getName() . '] is disabled');
        }

        if ($this->app->request->isCli() && !$this->source->isCron()) {
            throw new \Exception('[import_source_id=' . $this->source->getName() . '] is out of cron');
        }

        if (!$this->isNeed($fileUniqueHash, $whyNoReason)) {
            $this->log('***NO NEED TO IMPORT*** ' . $whyNoReason);

            if (!$this->app->isDev()) {
                return true;
            }
        }

        $this->createHistory($fileUniqueHash);
        $this->setOutOfStock();


        //@todo fixWhere (smth instead of partner_update_at) !!!!
        $fixWhere = (new FixWhere($this->app))
            ->setSources([$this->source])
//            ->setCreatedAtFrom($ts = time() - 1)
//            ->setOrBetweenCreatedAndUpdated(true)
//            ->setUpdatedAtFrom($ts)
            ->setPartnerUpdatedAtFrom($this->microtime = (int)microtime(true));

        $isOk = $this->import($offset, $length);

        $this->updateHistory($isOk);

        if (true || !$this->app->isDev()) {
            $this->app->utils->items->doFixWithNonExistingAttrs($fixWhere);
            $aff = $this->app->utils->attrs->doDeleteNonExistingItemsMva($fixWhere);
            $this->log('updated with invalid mva: ' . $aff);
//            $this->app->utils->attrs->doAddMvaByInclusions($fixWhere);
            $this->app->utils->items->doFixItemsCategories($fixWhere);
        }

        return $isOk;
    }

    public function __invoke(int $offset = 0, int $length = 9999999)
    {
        return $this->run($offset, $length);
    }

    public static function getPostEditableColumns()
    {
        return [
            'name',
            Category::getPk(),
            Country::getPk()
        ];
    }

    public function getItemTargetLink(Item $item)
    {
        return null;
    }

    protected function log(string $msg, string $type = Logger::TYPE_DEBUG)
    {
        $this->app->services->logger->make('import[' . $this->source->getId() . ']: ' . $msg, $type);
        return $this;
    }
}
