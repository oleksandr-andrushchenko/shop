<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 9/28/16
 * Time: 4:42 PM
 */

namespace SNOWGIRL_SHOP;

use KzykHys\CsvParser\CsvParser;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Helper;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Helper\FS as FsHelper;
use SNOWGIRL_CORE\Service\Logger;
use SNOWGIRL_CORE\Script;
use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_SHOP\Entity\Import\History as ImportHistory;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Image;

use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalogManager;

use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttr;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;

use SNOWGIRL_SHOP\Entity\Country;

use SNOWGIRL_SHOP\Item\FixWhere;

/**
 * Class Import
 * @property App app
 * @package SNOWGIRL_SHOP
 * @see https://csv.thephpleague.com/9.0/
 */
class Import
{
    /** @var App */
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

    protected $csvProcessorV2 = false;
    protected $mvaProcessorV2 = false;

    protected $csvFileDelimiter = ',';
    protected $csvFileEnclosure = '"';
    protected $csvFileEncoding = 'CP932';
    protected $csvFileHeader = false;

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
        if ($this->filters) {
            //@todo add check if actual
            $this->source->setFileFilter(Arrays::mergeRecursiveUnique($this->filters, $this->source->getFileFilter(true)));
        }

        return $this->source->getFileFilter(true);
    }

    protected function getMappings()
    {
        if ($this->mappings) {
            //@todo add check if actual
            $this->source->setFileMapping(Arrays::mergeRecursiveUnique($this->mappings, $this->source->getFileMapping(true)));
        }

        return $this->source->getFileMapping(true);
    }

    public function getMeta()
    {
        $output = [
            'columns' => [],
            'indexes' => []
        ];

        if ($this->csvProcessorV2) {
            foreach ($this->getCsvFileParser(0, 1) as $header) {
                $output['columns'] = $header;
                break;
            }
        } else {
            if ($handler = fopen($this->getCsvFile(), 'r')) {
                $output['columns'] = explode($this->csvFileDelimiter, rtrim(fgets($handler)));
                fclose($handler);
            }
        }

        $output['indexes'] = array_combine($output['columns'], range(0, count($output['columns']) - 1));

        return $output;
    }

    protected function setAccessPermissions($filename)
    {
        chgrp($filename, $this->app->config->server->web_server_group);
        chown($filename, $this->app->config->server->web_server_user);
        FsHelper::chmodRecursive($filename, 0775);
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
        return FsHelper::rmDir($this->getCachePath());
    }

    public function getDownloadedRawFileName()
    {
        return implode('/', [
            $this->getCachePath(),
            'src'
        ]);
    }

    public function getDownloadedTmpCsvFileName()
    {
        return implode('/', [
            $this->getCachePath(),
            'src.tmp'
        ]);
    }

    public function getDownloadedCsvFileName()
    {
        return implode('/', [
            $this->getCachePath(),
            'src.csv'
        ]);
    }

    /**
     * @return string
     */
    protected function getCsvFile()
    {
        clearstatcache(true, $file = $this->getDownloadedCsvFileName());

        if (!file_exists($file)) {
            clearstatcache(true, $file2 = $this->getDownloadedRawFileName());

            if (!file_exists($file2)) {
                $this->log('downloading file...');

                Helper::runShell(implode(' ', [
                    'wget',
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

    /**
     * @see https://packagist.org/packages/kzykhys/php-csv-parser
     * @param int $offset
     * @param int $limit
     * @return CsvParser
     * @throws Exception
     */
    protected function getCsvFileParser($offset = 0, $limit = -1)
    {
        return CsvParser::fromFile($this->getCsvFile(), [
            'delimiter' => $this->csvFileDelimiter,
            'enclosure' => $this->csvFileEnclosure,
            'encoding' => $this->csvFileEncoding,
            'offset' => $offset,
            'limit' => $limit,
            'header' => $this->csvFileHeader
        ]);
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
//                    var_dump($row);
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

        if ($this->csvProcessorV2) {
            $parser = $this->getCsvFileParser(1);

            foreach ($parser as $row) {
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

            return true;
        }

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

    /**
     * @param int $page
     * @param int $size
     * @return \stdClass
     */
    public function getData($page = 1, $size = 10)
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

    public function getFileColumnsValuesInfo(array $columns, $allowModifyOnly = true, array $allowModifyOnlyExclude = [])
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

    public function getItemColumnsToImport()
    {
        $output = array_diff(array_keys(Item::getColumns()), [
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
            'created_at',
            'updated_at'
        ]);

        if (ImportSource::TYPE_PARTNER != $this->source->getType()) {
            $output = array_diff($output, ['partner_link']);
        }

        return $output;
    }

    public function getRequiredItemColumnsToImport()
    {
        $output = array_diff($this->getItemColumnsToImport(), [
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

    protected function beforeImport()
    {

    }

    /**
     * @return Import
     */
    protected function setOutOfStock()
    {
        $this->app->managers->items->updateMany(['is_in_stock' => 0], [
            'vendor_id' => $this->source->getVendorId()
        ]);

        return $this;
    }

    protected $importValues;
    protected $importColumnsToImport;
    protected $importRequiredColumns;
    protected $importIsRuLang;
    protected $importIndex;
    protected $importBindValues;
    protected $importOkTotal;
    protected $importSkippedTotal;
    protected $importStartIndex;
    protected $importEndIndex;
    protected $importDefaultAllowModifyOnly = true;
    protected $importPartnerType;

    protected $importSport;
    protected $importSizePlus;
    protected $importTags;

    protected function import($offset = 0, $length = 9999999)
    {
        try {
            $this->beforeImport();

            $this->importColumnsToImport = self::getItemColumnsToImport();
            $this->importRequiredColumns = self::getRequiredItemColumnsToImport();

            $this->importIsRuLang = in_array('ru', $this->langs);

            $this->importSvaPrepare();

            if ($this->mvaProcessorV2) {
                $this->importMvaPrepare();
            }

            $this->importIndex = 0;
            $this->importBindValues = [];
            $this->importOkTotal = 0;
            $this->importSkippedTotal = 0;
            $this->importStartIndex = $offset;
            $this->importEndIndex = $offset + $length - 1;

            $this->importPartnerType = ImportSource::TYPE_PARTNER == $this->source->getType();

            $this->walkFilteredFile(function ($row, $k) {
                if ($k < $this->importStartIndex) {
                    return true;
                }

                if ($k > $this->importEndIndex) {
                    return false;
                }

                $this->importSport = null;
                $this->importSizePlus = null;
                $this->importTags = [];

                $ok = false;

                $values = [];

                while (true) {
                    $imageCount = null;

                    if (!$values['image'] = $this->importRowToImage($row, $imageCount)) {
                        break;
                    }

                    $values['image_count'] = $imageCount;

                    $values = array_merge($values, $this->importSvaRetrieve($row));

                    if (!$values['category_id'] || !$values['brand_id']) {
                        break;
                    }

                    if (!$values['name'] = $this->importRowToName($row)) {
                        break;
                    }

                    if (!$values['price'] = $this->importRowToPrice($row)) {
                        break;
                    }

                    if (!$values['upc'] = $this->importRowToUpc($row)) {
                        break;
                    }

                    if ($this->importPartnerType && !$values['partner_link'] = $this->importRowToPartnerLink($row)) {
                        break;
                    }

                    $values['import_source_id'] = $this->importRowToImportSource($row);
                    $values['old_price'] = $this->importRowToOldPrice($row);
                    $values['is_in_stock'] = $this->importRowToIsInStock($row);
                    $values['entity'] = $this->importRowToEntity($row);
                    $values['is_sport'] = $this->importSport ? 1 : 0;
                    $values['is_size_plus'] = $this->importSizePlus ? 1 : 0;

                    foreach (array_diff($this->importColumnsToImport, array_keys($values)) as $dbColumn) {
                        if (isset($this->mappings[$dbColumn])) {
                            $map = $this->mappings[$dbColumn];

                            $value = trim($row[$this->indexes[$map['column']]]);

                            if (array_key_exists('modify', $map) && array_key_exists($value, $modifies = $map['modify']) && null !== $modifies[$value]['value']) {
                                $value = $modifies[$value]['value'];
                            }

                            $values[$dbColumn] = $value;
                        }
                    }

                    foreach ($this->importRequiredColumns as $dbColumn) {
                        if (isset($values[$dbColumn]) && !mb_strlen($values[$dbColumn])) {
                            $this->log($dbColumn . '\' value is empty[' . var_export($values[$dbColumn], true) . '] ...ignoring record');
                            break;
                        }
                    }

                    if ($this->mvaProcessorV2) {
                        if ($this->importTags) {
                            $this->importMvaData['tag_id']['imageToArray'][$values['image']] = array_unique($this->importTags);
                        }

                        $this->importMvaRetrieve($row, $values['image']);
                    }

                    $ok = true;

                    break;
                }

                if ($ok) {
                    $this->importValues = $values;
                    $this->importBindValues = array_merge($this->importBindValues, array_values($values));
                    $this->importOkTotal++;
                    $this->importIndex++;
                } else {
                    $this->importSkippedTotal++;
                }

                if (1000 == $this->importIndex) {
                    $this->importInsertItems();
                    $this->importIndex = 0;
                    $this->importBindValues = [];

                    if ($this->app->isDev()) {
                        return false;
                    }
                }

                return true;
            }, $this->importDefaultAllowModifyOnly);

            if ($this->importIndex) {
                $this->importInsertItems();
            }

            if ($this->mvaProcessorV2) {
                $this->importMvaInsert();
            }

            return true;
        } catch (\Exception $ex) {
            $this->app->services->logger->makeException($ex);
            return false;
        }
    }

    protected $importSvaComponents;
    protected $importSvaData;

    protected function importSvaPrepare()
    {
        $this->importSvaComponents = PageCatalogManager::getSvaComponents();
        $this->importSvaData = [];

        foreach ($this->importSvaComponents as $entityClass) {
            /** @var string|Entity $entityClass */
            /** @var ItemAttrManager $manager */
            $manager = $this->app->managers->getByEntityClass($entityClass);

            $entity = $manager->getEntity();
            $table = $entity->getTable();

            $this->importSvaData[$entity->getPk()] = [
                'manager' => $manager,
                'entity' => $manager->getEntity(),
                'method' => 'importRowTo' . ucfirst($table),
                'nameToId' => $this->app->utils->attrs->getNameToId($entityClass, true),
                'termNameToId' => ($termsManager = $manager->getTermsManager())
                    ? $termsManager->getValueToComponentId(
                        isset($termsManager->getEntity()->getColumns()['lang']) ? ['lang' => $this->langs] : null
                    )
                    : [],
                'uriToId' => $this->app->utils->attrs->getUriToId($entityClass),
                'processNew' => !!$this->app->config->import->$table(false)
            ];
        }
    }

    protected function importSvaRetrieve($row)
    {
        $output = [];

        foreach ($this->importSvaData as $pk => &$data) {
            /** @var Entity[]|Manager[] $data */

            if ($rawNameOrId = $this->{$data['method']}($row)) {
                $id = 0;

                $rawNameOrId = trim($rawNameOrId);

                if (is_numeric($rawNameOrId)) {
                    $id = $rawNameOrId;
                } else {
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
                            foreach ($this->importSvaData as $data2) {
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
     * @param $pk
     * @param $row
     * @return int
     */
    protected function importRowToSvaAttr($pk, $row)
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

                    if ($this->importSvaData[$pk]['processNew']) {
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
                        if ($this->importIsRuLang) {
                            foreach ($this->importSvaData[$pk]['nameToId'] as $value => $id) {
                                if (false !== mb_stripos($source, $value)) {
                                    return (int)$id;
                                }
                            }
                        }

                        foreach ($this->importSvaData[$pk]['termNameToId'] as $term => $id) {
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

    protected function importRowToCategory($row)
    {
        $pk = 'category_id';
        $map = $this->mappings[$pk];

        if (isset($map['column'])) {
            $value = trim($row[$this->indexes[$map['column']]]);

            if (array_key_exists('modify', $map) && array_key_exists($value, $modifies = $map['modify']) && $modifies[$value]['value']) {
                $this->importTags = array_merge($this->importTags, $modifies[$value]['tags']);
                $this->importSport = $this->importSport || in_array('is_sport', $modifies[$value]);
                $this->importSizePlus = $this->importSizePlus || in_array('is_size_plus', $modifies[$value]);
                return (int)$modifies[$value]['value'];
            }

            return $this->importRowToSvaAttr($pk, $row);
        } elseif ($map['value']) {
            return (int)$map['value'];
        }

        return null;
    }

    protected function importRowToBrand($row)
    {
        return $this->importRowToSvaAttr('brand_id', $row);
    }

    protected function importRowToCountry($row)
    {
        return $this->importRowToSvaAttr('country_id', $row);
    }

    protected function importRowToVendor($row)
    {
        return $this->source->getVendorId();
    }

    protected function importRowToImportSource($row)
    {
        return $this->source->getId();
    }

    protected $importMvaComponents;
    protected $importMvaData;

    protected function importMvaPrepare()
    {
        $this->importMvaComponents = PageCatalogManager::getMvaComponents();
        $this->importMvaData = [];

        foreach ($this->importMvaComponents as $entityClass) {
            /** @var string|Entity $entityClass */
            /** @var ItemAttrManager $manager */
            $manager = $this->app->managers->getByEntityClass($entityClass);

            $entity = $manager->getEntity();
            $table = $entity->getTable();

            $this->importMvaData[$entity->getPk()] = [
                'entity' => $manager->getEntity(),
                'manager' => $manager,
                'method' => 'importRowTo' . ucfirst($entity->getTable()) . 's',
                'nameToId' => $this->app->utils->attrs->getNameToId($entityClass, true),
                'termNameToId' => ($termsManager = $manager->getTermsManager())
                    ? $termsManager->getValueToComponentId(
                        isset($termsManager->getEntity()->getColumns()['lang']) ? ['lang' => $this->langs] : null
                    )
                    : [],
                'uriToId' => $this->app->utils->attrs->getUriToId($entityClass),
                'imageToArray' => [],
                'processNew' => !!$this->app->config->import->$table(false)
            ];
        }
    }

    protected function importMvaRetrieve($row, $image)
    {
        foreach ($this->importMvaData as $pk => $data) {
            if ($tmp = $this->{$data['method']}($row)) {
                if (!isset($this->importMvaData[$pk]['imageToArray'][$image])) {
                    $this->importMvaData[$pk]['imageToArray'][$image] = [];
                }

                $this->importMvaData[$pk]['imageToArray'][$image] = array_merge($this->importMvaData[$pk]['imageToArray'][$image], $tmp);
            }
        }
    }


    protected function importMvaInsert()
    {
        $itemPk = $this->app->managers->items->getEntity()->getPk();

        foreach ($this->importMvaComponents as $entityClass) {
            try {
                /** @var string|Entity $entityClass */

                /** @var ItemAttrManager $manager */
                $manager = $this->app->managers->getByEntityClass($entityClass);
                /** @var ItemAttr $entity */
                $entity = $manager->getEntity();

                $entityPk = $entity->getPk();

                $data = $this->importMvaData[$entityPk];

                if (count($data)) {
                    $entityTable = $entity->getTable();

                    $insert = [];

                    $imageToId = $this->app->managers->items->getImageToId(['image' => array_keys($data['imageToArray'])]);

                    foreach ($data['imageToArray'] as $image => $rawArray) {
                        if (!isset($imageToId[$image])) {
                            $this->log('$imageToId[' . $image . '] not found... skipping...');
                            continue;
                        }

                        $rawArray = array_map(function ($i) {
                            return trim($i);
                        }, $rawArray);

                        $array = array_map(function ($i) {
                            return mb_strtolower($i);
                        }, $rawArray);

                        $array = array_combine($rawArray, $array);
                        $array = array_unique($array);

                        foreach ($array as $rawNameOrId => $nameOrId) {
                            $id = null;

                            if (is_numeric($nameOrId)) {
                                $id = $nameOrId;
                            } elseif (isset($data['nameToId'][$nameOrId])) {
                                $id = $data['nameToId'][$nameOrId];
                            } elseif (isset($data['uriToId'][$newUri = $entity::normalizeUri($nameOrId)])) {
                                $id = $data['uriToId'][$newUri];
                                $this->importMvaData[$entityPk]['nameToId'][$nameOrId] = $id;
                            } else {
                                try {
                                    while (true) {
                                        foreach ($this->importSvaData as $data2) {
                                            if (isset($data2['nameToId'][$nameOrId])) {
                                                break 2;
                                            }
                                        }

                                        foreach ($this->importMvaData as $data2) {
                                            if (isset($data2['nameToId'][$nameOrId])) {
                                                break 2;
                                            }
                                        }

                                        /** @var ItemAttr $object */
                                        $object = new $entityClass(['name' => $rawNameOrId]);
                                        $id = $manager->insertOne($object);
                                        $this->importMvaData[$entityPk]['uriToId'][$object->getUri()] = $id;
                                        $this->importMvaData[$entityPk]['nameToId'][$nameOrId] = $id;

                                        break;
                                    }
                                } catch (\Exception $ex) {
                                    $this->app->services->logger->makeException($ex);

                                    if (Exception::_check($ex, 'Duplicate entry')) {
                                        $tmp = $this->app->services->rdbms->selectOne($entityTable, new Query([
                                            'columns' => $entityPk,
                                            'where' => ['uri' => $newUri]
                                        ]));

                                        if ($tmp) {
                                            $id = $tmp[$entityPk];
                                            $this->importMvaData[$entityPk]['uriToId'][$newUri] = $id;
                                            $this->importMvaData[$entityPk]['nameToId'][$nameOrId] = $id;
                                        } else {
                                            $this->log('Can\'t figure out ' . $entityTable . ' id in case of duplicate');
                                        }
                                    } else {
                                        $this->log('Can\'t figure out ' . $entityTable . ' id');
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

                    $aff = $insert ? $manager->getMvaLinkManager()->insertMany($insert, true) : 0;
                } else {
                    $aff = 0;
                }

                $this->log('Affected items ' . $itemPk . ': ' . $aff);
            } catch (\Exception $ex) {
                $this->log('Error on mva[' . $itemPk . '] insert: ' . $ex->getMessage());
                $this->app->services->logger->makeException($ex);
            }
        }
    }

    /**
     * Returns IDs
     *
     * @param $pk
     * @param $row
     * @return array
     */
    protected function importRowToMvaAttr($pk, $row)
    {
        $output = [];

        if (isset($this->sources[$pk])) {
            foreach ((array)$this->sources[$pk] as $c) {
                if (isset($this->indexes[$c])) {
                    $i = $this->indexes[$c];

                    if ($source = trim($row[$i])) {
                        if ($this->importIsRuLang) {
                            foreach ($this->importMvaData[$pk]['nameToId'] as $value => $id) {
                                if (false !== mb_stripos($source, $value)) {
                                    $output[] = $id;
                                }
                            }
                        }

                        if (0 == count($output)) {
                            foreach ($this->importMvaData[$pk]['termNameToId'] as $term => $id) {
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

    protected function importRowToColors($row)
    {
        return $this->importRowToMvaAttr('color_id', $row);
    }

    protected function importRowToSizes($row)
    {
        return $this->importRowToMvaAttr('size_id', $row);
    }

    protected function importRowToMaterials($row)
    {
        return $this->importRowToMvaAttr('material_id', $row);
    }

    protected function importRowToSeasons($row)
    {
        return $this->importRowToMvaAttr('season_id', $row);
    }

    protected function importRowToTags($row)
    {
        return $this->importRowToMvaAttr('tag_id', $row);
    }

    protected function importRowToName($row)
    {
        return trim($row[$this->indexes[$this->mappings['name']['column']]]);
    }

    protected function importRowToImage($row, &$count = 1)
    {
        $images = array_map('trim', explode(',', $row[$this->indexes[$this->mappings['image']['column']]]));

        $first = array_shift($images);

        $error = null;

        if (!$image = Image::download($first, null, $error)) {
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

                if (Image::download($im, $newImage, $error)) {
                    $count++;
                } else {
                    $this->log($im . ': ' . $error, Logger::TYPE_ERROR);
                }
            }
        }

        return $image;
    }

    protected function importRowToPrice($row)
    {
        $v = (float)trim($row[$this->indexes[$this->mappings['price']['column']]]);

        if ($v > 999999.99) {
            return false;
        }

        return number_format($v, 2, '.', '');
    }

    /**
     * @todo use Item Entity's Columns info...
     * @param $row
     * @return float|null
     */
    protected function importRowToOldPrice($row)
    {
        if (isset($this->mappings['old_price']) && $v = (float)trim($row[$this->indexes[$this->mappings['old_price']['column']]])) {
            if ($v > 999999.99) {
                return null;
            }

            return number_format($v, 2, '.', '');
        }

        return null;
    }

    protected function importRowToUpc($row)
    {
        return trim($row[$this->indexes[$this->mappings['upc']['column']]]);
    }

    protected function importRowToIsInStock($row)
    {
        $map = $this->mappings['is_in_stock'];
        $value = trim($row[$this->indexes[$map['column']]]);

        if (array_key_exists('modify', $map) && array_key_exists($value, $modifies = $map['modify'])) {
            return (int)$modifies[$value]['value'];
        }

        return 1;
    }

    protected function importRowToPartnerLink($row)
    {
        return trim($row[$this->indexes[$this->mappings['partner_link']['column']]]);
    }

    protected function importRowToEntity($row)
    {
        if (isset($this->mappings['entity'])) {
            $map = $this->mappings['entity'];
            $value = trim($row[$this->indexes[$this->mappings['entity']['column']]]);

            if (array_key_exists('modify', $map) && array_key_exists($value, $modifies = $map['modify']) && strlen($modifies[$value]['value'])) {
                $this->importTags = array_merge($this->importTags, $modifies[$value]['tags']);
                $this->importSport = $this->importSport || in_array('is_sport', $modifies[$value]);
                $this->importSizePlus = $this->importSizePlus || in_array('is_size_plus', $modifies[$value]);
                $value = $modifies[$value]['value'];
            }

            return $value;
        }

        return '';
    }

    protected function _importInsertItems()
    {
        $this->log('***Import Progress***')
            ->log('OK items = ' . $this->importOkTotal)
            ->log('SKIPPED items = ' . $this->importSkippedTotal);

        if ((!$this->importValues) || (!$this->importBindValues) || (!$this->importIndex)) {
            return false;
        }

        $columnsOptions = Item::getColumns();

        $tmp = [];

        for ($i = 0, $s = count($this->importValues); $i < $this->importIndex; $i++) {
            $tmp[] = implode(', ', array_fill(0, $s, '?'));
        }

        $onDuplicateIgnoreColumns = [
            $this->app->managers->items->getEntity()->getPk(),
            $this->app->managers->vendors->getEntity()->getPk(),
            $this->app->managers->sources->getEntity()->getPk(),
            'image',
            'upc',
            'created_at',
            'updated_at'
        ];

        $editableColumns = self::getPostImportEditableColumns();

        $db = $this->app->services->rdbms;

        $onDuplicateClosure = function ($column, $value) use ($db) {
            return $db->quote($column) . ' = IF(' . implode(', ', [
                    //if duplicate on image - then
                    $db->quote('image') . ' = VALUES(' . $db->quote('image') . ')',
                    //ignore (do nothing)
                    $db->quote($column),
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
            }, array_keys($this->importValues))) . ') VALUES ' . implode(', ', array_map(function ($i) {
                return '(' . $i . ')';
            }, $tmp)),
            'ON DUPLICATE KEY UPDATE',

            //@todo next code overwrites all items fixes... (+add fixWhere clauses maybe?! +take into account "updated_at" in this query)

            implode(', ', array_map(function ($column) use ($onDuplicateClosure, $db) {
                $value = 'VALUES(' . $db->quote($column) . ')';
                return $onDuplicateClosure($column, $value);
            }, array_diff(array_keys($this->importValues), $onDuplicateIgnoreColumns, $editableColumns))) . ' ,',
            implode(', ', array_map(function ($column) use ($onDuplicateClosure, $db, $columnsOptions) {
                $options = $columnsOptions[$column];

                $value = 'IF(' . implode(', ', [
                        implode(' OR ', array_filter([
                            !in_array(Entity::REQUIRED, $options) ? ($db->quote($column) . ' IS NULL') : null,
                            Entity::COLUMN_INT == $options['type'] ? ($db->quote($column) . ' = 0') : null,
                            Entity::COLUMN_TEXT == $options['type'] ? ($db->quote($column) . ' = \'\'') : null
                        ], function ($v) {
                            return null !== $v;
                        })),
                        'VALUES(' . $db->quote($column) . ')',
                        $db->quote($column)
                    ]) . ')';

                return $onDuplicateClosure($column, $value);
            }, $editableColumns)) . ' ,',
            $onDuplicateClosure('updated_at', 'NOW()')
        ]);
        $query->params = $this->importBindValues;
        $query->log = false;

        return $db->req($query)->affectedRows();
    }

    protected function importInsertItems()
    {
        try {
            $aff = $this->_importInsertItems();
            $this->log('Affected items: ' . (int)$aff);
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

    /**
     * @return null|Entity|ImportHistory
     * @throws \Exception
     */
    protected function getLastHistory()
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
     * @param null $uniqueFileHash
     * @param null $whyNoReason
     * @return bool
     * @throws \Exception
     */
    protected function isNeed(&$uniqueFileHash = null, &$whyNoReason = null)
    {
        if ($history = $this->getLastHistory()) {
            if ($history->isOk() || (time() - strtotime($this->source->getCreatedAt()) >= 7 * 24 * 60 * 60)) {
                if (!$this->app->isDev()) {
                    $this->dropCache();
                }

                $uniqueFileHash = $this->getUniqueFileHash();

                if ($uniqueFileHash != $history->getFileUniqueHash()) {
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

    /** @var ImportHistory */
    protected $history;

    /**
     * @param $fileUniqueHash
     * @return $this
     * @throws Exception\EntityAttr\Required
     */
    protected function createHistory($fileUniqueHash)
    {
        $this->history = (new ImportHistory)
            ->setImportSourceId($this->source->getId())
            ->setFileUniqueHash($fileUniqueHash);

        $this->app->managers->importHistory->insertOne($this->history);

        $this->log('History id: ' . $this->history->getId());

        return $this;
    }

    /**
     * @param $isOk
     * @return $this
     * @throws \Exception
     */
    protected function updateHistory($isOk)
    {
        if ($this->history && $this->history->getId()) {
            $this->history->setIsOk($isOk);
            $this->app->managers->importHistory->updateOne($this->history);
        } else {
            $this->log('History can\'t being updated');
        }

        return $this;
    }

    /** @var FixWhere */
    protected $importFixWhere;

    /**
     * @param int $offset
     * @param int $length
     *
     * @return bool
     * @throws Exception\EntityAttr\Required
     */
    public function run($offset = 0, $length = 9999999)
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

        $this->importFixWhere = (new FixWhere($this->app))
            ->setSources([$this->source])
            //@todo replace with last id
            ->setCreatedAtFrom($ts = time() - 1)
            ->setOrBetweenCreatedAndUpdated(true)
            ->setUpdatedAtFrom($ts);

        $isOk = $this->import($offset, $length);
        $this->updateHistory($isOk);

        if (true || !$this->app->isDev()) {
//            $this->app->utils->items->doDeleteItemsWithInvalidCategories($this->importFixWhere);
//            $this->app->utils->items->doDeleteWithNonExistingCategories();
//            $this->app->utils->items->doDeleteItemsWithInvalidBrands($this->importFixWhere);
//            $this->app->utils->items->doDeleteWithNonExistingBrands();
            $this->app->utils->items->doFixWithNonExistingAttrs();

            if (!$this->mvaProcessorV2) {
                $this->app->utils->attrs->doAddMvaByInclusions($this->importFixWhere);
            }

            //@todo flush ->setOrBetweenCreatedAndUpdated(true)->setUpdatedAtFrom($ts)
            $this->app->utils->items->doFixItemsCategories($this->importFixWhere);
        }

        return $isOk;
    }

    public static function getPostImportEditableColumns()
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

    /**
     * @param $msg
     * @param string $type
     * @return Import
     */
    protected function log($msg, $type = Logger::TYPE_DEBUG)
    {
        $this->app->services->logger->make('import[' . $this->source->getId() . ']: ' . $msg, $type);
        return $this;
    }
}
