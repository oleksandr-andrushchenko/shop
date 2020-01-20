<?php

namespace SNOWGIRL_SHOP;

use SNOWGIRL_CORE\App;
use SNOWGIRL_CORE\File;
use SNOWGIRL_SHOP\App\Web;
use SNOWGIRL_SHOP\App\Console;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Helper\Strings;
use SNOWGIRL_CORE\Helper\FileSystem;
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
use DateTime;

/**
 * Class Import (for ImportSource::TYPE_PARTNER)
 *
 * @todo    for ImportSource::TYPE_OWN create separate class
 * @todo    use compose instead of extend
 *
 * @package SNOWGIRL_SHOP
 */
class Import
{
    protected const LIMIT = 500;

    /**
     * @var Web|Console
     */
    protected $app;

    /**
     * @var ImportSource
     */
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
    protected $isForceUpdate;
    protected $debug;

    public function __construct(App $app, ImportSource $source)
    {
        $this->app = $app;
        $this->source = $source;

        $this->initMeta();

        $this->filters = $this->getFilters();
        $this->mappings = $this->getMappings();

        $this->debug = $app->isDev();
    }

    protected function initMeta(): self
    {
        $meta = $this->getMeta();
        $this->indexes = $meta['indexes'];
        $this->columns = $meta['columns'];

        return $this;
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

    public function getFilename(): string
    {
        return $this->source->getFile();
    }

    public function getMeta()
    {
        $output = [
            'columns' => [],
            'indexes' => []
        ];

        if ($handler = fopen($this->getDownloadedCsvFileName(), 'r')) {
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
        FileSystem::chmodRecursive($filename, 0775);
        return $this;
    }

    protected function getCsvFilename($tmp = null): string
    {
        return implode('/', [
            $this->app->dirs['@tmp'],
            implode('_', [
                'import_source',
                $this->source->getId(),
                ($tmp ?: md5($this->getFilename())) . '.csv'
            ])
        ]);
    }

    protected $cacheDropped;

    public function dropCache(): ?bool
    {
        if (!$this->cacheDropped) {
            $this->log('dropping cache...');

            if ($this->checkPid()) {
                $this->log('previous running...');
                return null;
            }

            try {
                FileSystem::deleteFilesByPattern($this->getCsvFilename('*'));
                $this->app->managers->importHistory->deleteMany(['import_source_id' => $this->source->getId()]);
                $this->history = null;
                $this->initMeta();

                $this->cacheDropped = true;
            } catch (\Throwable $exception) {
                return false;
            }
        }

        return $this->cacheDropped;
    }

    public function getDownloadedCsvFileName(): string
    {
        $file = $this->getCsvFilename();

        if (!FileSystem::isFileExists($file)) {
            $this->log('downloading file...');

            shell_exec(implode(' ', [
                'wget --quiet',
                '--output-document=' . $file,
                '"' . $this->getFilename() . '"'
            ]));

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

    protected function deleteOldFiles(): int
    {
        $aff = 0;

        $current = $this->getCsvFilename();

        foreach (glob($this->getCsvFilename('*')) as $file) {
            if ($current != $file) {
                if (FileSystem::deleteFile($file)) {
                    $aff++;
                }
            }
        }

        return $aff;
    }

    protected function readFileRow($handle, $delimiter): array
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

    protected $walkTotal;
    protected $walkFilteredFilter;
    protected $walkFilteredModifier;
    protected $walkFileSize;

    public function walkFilteredFile(\Closure $fn, bool $allowModifyOnlyCheck = null, array $allowModifyOnlyExclude = [])
    {
        if (null === $allowModifyOnlyCheck) {
            $allowModifyOnlyCheck = $this->defaultAllowModifyOnly;
        }

        $this->walkTotal = 0;
        $this->walkFilteredFilter = 0;
        $this->walkFilteredModifier = 0;

        $this->walkFileSize = count($this->columns);

        $i = 0;

        if (!$handle = fopen($this->getDownloadedCsvFileName(), 'r')) {
            return false;
        }

        //skip first line (columns)
        fgets($handle);

        while ($row = self::readFileRow($handle, $this->csvFileDelimiter)) {
            if (count($row) != $this->walkFileSize) {
                continue;
            }

            $this->walkTotal++;

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

            $this->walkFilteredFilter++;

            if ($allowModifyOnlyCheck) {
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

                $this->walkFilteredModifier++;
            }

            $row = $this->postNormalizeRow($row);

            if (false === $fn($row, $i++)) {
                break;
            }

//            if ($this->app->isDev() && (100 == $i)) {
//                break;
//            }
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

        $rows = [];
        $startIndex = ($page - 1) * $size;
        $endIndex = $page * $size - 1;

        $this->walkFilteredFile(function ($row, $i) use ($startIndex, $endIndex, &$rows) {
            if ($i < $startIndex || $i > $endIndex) {
                return true;
            }

            $rows[] = $row;
        });

        $return->data = $rows;
        $return->totalItems = $this->walkTotal;
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
        return array_diff(array_keys($this->app->managers->items->getEntity()->getColumns()), [
            $this->app->managers->items->getEntity()->getPk(),
            $this->app->managers->sources->getEntity()->getPk(),
            $this->app->managers->vendors->getEntity()->getPk(),
            'rating',
            'is_sport',
            'is_size_plus',

            'order_desc_relevance',
            'order_desc_rating',
            'order_asc_price',
            'order_desc_price',

            'partner_link_hash',

            'created_at',
            'updated_at'
        ]);
    }

    public function getRequiredItemColumns()
    {
        return array_diff($this->getItemColumns(), [
            $this->app->managers->countries->getEntity()->getPk(),
            'old_price',
            'description',
            'entity',
            'is_in_stock',
            $this->app->managers->sources->getEntity()->getPk()
        ]);
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

    protected $keys;
    protected $itemColumns;
    protected $requiredColumns;
    protected $isRuLang;
    protected $index;
    protected $bindValues;
    protected $passed;
    protected $skippedByOther;
    protected $skippedByUnique;
    protected $skippedByUpdated;
    protected $defaultAllowModifyOnly = true;
    protected $partnerType;

    protected $sport;
    protected $sizePlus;

    protected $microtime;
    protected $aff;
    protected $error;

    protected function getDbItems()
    {
        return $this->app->managers->items->clear()
            ->setColumns([
                $this->app->managers->items->getEntity()->getPk(),
                'partner_item_id',
                'category_id',
                'partner_link_hash',
                'image'
            ])
            ->setWhere(['import_source_id' => $this->source->getId()])
            ->getItems();
    }

    protected function getPartnerItemIdByImage(array $image)
    {
        $output = [];

        $tmp = $this->app->managers->items->clear()
            ->setColumns(['partner_item_id', 'image'])
            ->setWhere(['import_source_id' => $this->source->getId(), 'image' => $image])
            ->setQueryParam('log', $this->debug)
            ->getArrays();

        foreach ($tmp as $v) {
            $output[$v['image']] = $v['partner_item_id'];
        }

        return $output;
    }

    protected function getPartnerUpdatedAtByPartnerItemId(array $partnerItemId)
    {
        $output = [];

        foreach ($this->app->managers->items->clear()
                     ->setColumns(['partner_item_id', 'partner_updated_at'])
                     ->setWhere(['import_source_id' => $this->source->getId(), 'partner_item_id' => $partnerItemId])
                     ->setQueryParam('log', $this->debug)
                     ->getItems() as $v) {
            $output[$v['partner_item_id']] = $v['partner_updated_at'];
        }

        return $output;
    }

    protected function getPartnerItemIdByPartnerItemId(array $partnerItemId)
    {
        $output = [];

        foreach ($this->app->managers->items->clear()
                     ->setColumns(['partner_item_id'])
                     ->setWhere(['import_source_id' => $this->source->getId(), 'partner_item_id' => $partnerItemId])
                     ->setQueryParam('log', $this->debug)
                     ->getItems() as $v) {
            $output[] = $v['partner_item_id'];
        }

        return $output;
    }

    protected function getItemIdByPartnerItemId(array $partnerItemId)
    {
        $output = [];

        $pk = $this->app->managers->items->getEntity()->getPk();

        foreach ($this->app->managers->items->clear()
                     ->setColumns([$pk, 'partner_item_id'])
                     ->setWhere(['import_source_id' => $this->source->getId(), 'partner_item_id' => $partnerItemId])
                     ->setQueryParam('placeholders', false)
                     ->setQueryParam('log', $this->debug)
                     ->getItems() as $item) {
            $output[$item['partner_item_id']] = $item[$pk];
        }

        return $output;
    }

    protected function getImageByItemId(array $itemId)
    {
        $output = [];

        $pk = $this->app->managers->items->getEntity()->getPk();

        foreach ($this->app->managers->items->clear()
                     ->setColumns([$pk, 'image'])
                     ->setWhere(['import_source_id' => $this->source->getId(), 'item_id' => $itemId])
                     ->setQueryParam('placeholders', false)
                     ->setQueryParam('log', $this->debug)
                     ->getItems() as $item) {
            $output[$item[$pk]] = $item['image'];
        }

        return $output;
    }

    protected $sva;

    protected function prepareSva()
    {
        $this->sva = [];

        foreach ($this->app->managers->catalog->getSvaComponents() as $entityClass) {
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

    protected function getAllSvaByRow($row)
    {
        $output = [];

        foreach ($this->sva as $entityPk => &$data) {
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
                        } catch (\Throwable $ex) {
                            $this->app->services->logger->makeException($ex);
                            $this->log('can\'t insert entity: ' . var_export($entity, true));
                        }
                    }
                }

                $output[$entityPk] = (int)$id;
            } else {
                $output[$entityPk] = null;
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
        if (array_key_exists('_category_id', $row)) {
            return $row['_category_id'];
        }

        # @todo checkout && test
        $partnerItemId = $this->getPartnerItemIdByRow($row);

        if (isset($this->dbRows[$partnerItemId])) {
            return $this->dbRows[$partnerItemId]['category_id'];
        }

        $map = $this->mappings['category_id'];

        if (isset($map['column'])) {
            $value = trim($row[$this->indexes[$map['column']]]);

            if (array_key_exists('modify', $map) && array_key_exists($value, $modifies = $map['modify']) && $modifies[$value]['value']) {
                $this->rememberMva($this->getPartnerItemIdByRow($row), 'tag_id', $modifies[$value]['tags']);
                $this->sport = $this->sport || in_array('is_sport', $modifies[$value]);
                $this->sizePlus = $this->sizePlus || in_array('is_size_plus', $modifies[$value]);
                return (int)$modifies[$value]['value'];
            }

            return $this->getSvaByRow('category_id', $row);
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

    protected $images;

    protected function rememberImages($partnerItemId, $value)
    {
        if (isset($this->images[$partnerItemId])) {
            $this->images[$partnerItemId] = array_merge($this->images[$partnerItemId], $value);
        } else {
            $this->images[$partnerItemId] = $value;
        }
    }

    protected $mva;

    protected function prepareMva()
    {
        $this->mva = [];

        foreach ($this->app->managers->catalog->getMvaComponents() as $entityClass) {
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
                'values' => [],
                'processNew' => !!$this->app->config->import->$table(false)
            ];
        }
    }

    protected function rememberMva($partnerItemId, $pk, $value)
    {
        if (isset($this->mva[$pk]['values'][$partnerItemId])) {
            $this->mva[$pk]['values'][$partnerItemId] = array_merge($this->mva[$pk]['values'][$partnerItemId], $value);
        } else {
            $this->mva[$pk]['values'][$partnerItemId] = $value;
        }
    }

    protected function rememberAllMvaByRow($row, $partnerItemId = null)
    {
        $partnerItemId = $partnerItemId ?: $this->getPartnerItemIdByRow($row);

        foreach ($this->mva as $pk => $data) {
            if ($tmp = $this->{$data['method']}($row)) {
                $this->rememberMva($partnerItemId, $pk, $tmp);
            }
        }
    }

    /**
     * Should be always synced with self::getCategoryByRow()
     *
     * @param      $row
     * @param null $partnerItemId
     */
    protected function rememberManualTagsByRow($row, $partnerItemId = null)
    {
        $partnerItemId = $partnerItemId ?: $this->getPartnerItemIdByRow($row);

        $map = $this->mappings['category_id'];

        if (isset($map['column'])) {
            $value = trim($row[$this->indexes[$map['column']]]);

            if (array_key_exists('modify', $map) && array_key_exists($value, $modifies = $map['modify']) && $modifies[$value]['value']) {
                $this->rememberMva($partnerItemId, 'tag_id', $modifies[$value]['tags']);
            }
        }
    }

    protected function getDownloadedImagesByRow($row)
    {
        $images = $this->getImagesByRow($row);

        if ($this->downloadImages) {
            $output = [];

            foreach (explode(',', $row[$this->indexes[$this->mappings['image']['column']]]) as $k => $url) {
                if (!in_array($images[$k], $this->existingImages)) {
                    if ($url = trim($url)) {
                        $error = null;

                        if (!$this->app->images->downloadWithWget($url, $images[$k], $error)) {
                            $this->log($url . ': ' . $error, Logger::TYPE_ERROR);
                            continue;
                        }
                    }
                }

                $output[] = $images[$k];
            }

            return $output;
        }

        return $images;
    }

    protected function rememberDownloadedImagesByRow($row, $partnerItemId = null)
    {
        $partnerItemId = $partnerItemId ?: $this->getPartnerItemIdByRow($row);

        $this->rememberImages($partnerItemId, $this->getDownloadedImagesByRow($row));
    }

    protected function insertImages()
    {
        try {
            $manager = $this->app->managers->itemImages;
            $table = $manager->getEntity()->getTable();

            $insert = [];

            $partnerItemIdToItemId = $this->getItemIdByPartnerItemId(array_keys($this->images));
            $itemIdToImage = $this->getImageByItemId(array_values($partnerItemIdToItemId));

            foreach ($this->images as $partnerItemId => $images) {
                if (!isset($partnerItemIdToItemId[$partnerItemId])) {
                    $this->log('$partnerItemIdToItemId[' . $partnerItemId . '] not found... skipping...');
                    continue;
                }

                $images = array_unique($images, SORT_REGULAR);

                $mainImage = $itemIdToImage[$partnerItemIdToItemId[$partnerItemId]];

                foreach ($images as $image) {
                    if ($mainImage != $image) {
                        $insert[] = [
                            'item_id' => (int)$partnerItemIdToItemId[$partnerItemId],
                            'image_id' => $image
                        ];
                    }
                }
            }

            $aff = $insert ? $manager->insertMany($insert, ['ignore' => true, 'log' => $this->debug]) : 0;

            $this->log('AFF ' . $table . ': ' . $aff);
        } catch (\Throwable $ex) {
            $this->log('Error on image[' . $table . '] insert: ' . $ex->getMessage());
            $this->app->services->logger->makeException($ex);
        }

        $this->images = [];
    }

    protected function insertMva()
    {
        $itemPk = $this->app->managers->items->getEntity()->getPk();

        foreach ($this->mva as $entityPk => $data) {
            try {
                /** @var ItemAttrManager $manager */
                $manager = $data['manager'];

                /** @var ItemAttr $entity */
                $entity = $data['entity'];

                /** @var string|Entity $entityClass */
                $entityClass = $entity->getClass();

                $table = $entity->getTable();

                $insert = [];

                $partnerItemIdToItemId = $this->getItemIdByPartnerItemId(array_keys($data['values']));

                foreach ($data['values'] as $partnerItemId => $rawArray) {
                    if (!isset($partnerItemIdToItemId[$partnerItemId])) {
                        $this->log('$partnerItemIdToItemId[' . $partnerItemId . '] not found... skipping...');
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
//                            $nameOrId = mb_strtolower($entity->normalizeText($rawNameOrId));

                            if (isset($data['nameToId'][$nameOrId])) {
                                $id = $data['nameToId'][$nameOrId];
                            } elseif (isset($data['uriToId'][$newUri = $entity->normalizeUri($nameOrId)])) {
                                $id = $data['uriToId'][$newUri];
                                $this->mva[$entityPk]['nameToId'][$nameOrId] = $id;
                            } elseif ($this->mva[$entityPk]['processNew']) {
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
                                } catch (\Throwable $ex) {
                                    $this->app->services->logger->makeException($ex);

                                    if (Exception::_check($ex, 'Duplicate entry')) {
                                        $tmp = $this->app->storage->mysql->selectOne($table, new Query([
                                            'columns' => $entityPk,
                                            'where' => ['uri' => $newUri]
                                        ]));

                                        if ($tmp) {
                                            $id = $tmp[$entityPk];
                                            $this->mva[$entityPk]['uriToId'][$newUri] = $id;
                                            $this->mva[$entityPk]['nameToId'][$nameOrId] = $id;
                                        } else {
                                            $this->log('Can\'t figure out ' . $table . ' id in case of duplicate');
                                        }
                                    } else {
                                        $this->log('Can\'t figure out ' . $table . ' id');
                                    }
                                }
                            }
                        }

                        if ($id) {
                            $insert[] = [
                                $itemPk => (int)$partnerItemIdToItemId[$partnerItemId],
                                $entityPk => (int)$id
                            ];
                        }
                    }
                }

                $aff = $insert ? $manager->getMvaLinkManager()->insertMany($insert, ['ignore' => true, 'log' => $this->debug]) : 0;

                $this->log('AFF ' . $manager->getMvaLinkManager()->getEntity()->getTable() . ': ' . $aff);
            } catch (\Throwable $ex) {
                $this->log('Error on mva[' . $manager->getMvaLinkManager()->getEntity()->getTable() . '] insert: ' . $ex->getMessage());
                $this->app->services->logger->makeException($ex);
            }

            $this->mva[$entityPk]['values'] = [];
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
        if (array_key_exists('_name', $row)) {
            return $row['_name'];
        }

        return trim($row[$this->indexes[$this->mappings['name']['column']]]);
    }

    protected function getImagesByRow($row)
    {
        if (array_key_exists('_images', $row)) {
            return $row['_images'];
        }

        $output = [];

        foreach (explode(',', $row[$this->indexes[$this->mappings['image']['column']]]) as $k => $url) {
            if ($url = trim($url)) {
                # @todo pass categoryId salt in case of unique accross categories (take into account: cats could changed, maps could chaned)
                $output[$k] = $this->app->images->getHash($url);
            }
        }

        return $output;
    }

    protected function getDownloadedImageByRow($row)
    {
        $downloaded = $this->getDownloadedImagesByRow($row);

        $image = array_shift($downloaded);

        $this->rememberImages($this->getPartnerItemIdByRow($row), $downloaded);

        return $image;
    }

    protected function getPriceByRow($row)
    {
        if (array_key_exists('_price', $row)) {
            return $row['_price'];
        }

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
        if (array_key_exists('_partner_item_id', $row)) {
            return $row['_partner_item_id'];
        }

        return trim($row[$this->indexes[$this->mappings['partner_item_id']['column']]]);
    }

    protected function getIsInStockByRow($row)
    {
        if (isset($this->mappings['is_in_stock'])) {
            $map = $this->mappings['is_in_stock'];

            if (array_key_exists('modify', $map) && array_key_exists($value = trim($row[$this->indexes[$map['column']]]), $modifies = $map['modify'])) {
                return (int)$modifies[$value]['value'];
            }
        }

        return 1;
    }

    protected function getPartnerLinkByRow($row)
    {
        if (array_key_exists('_partner_link', $row)) {
            return $row['_partner_link'];
        }

        return trim($row[$this->indexes[$this->mappings['partner_link']['column']]]);
    }

    protected function normalizePartnerLink($link)
    {
        return $link;
    }

    protected function getPartnerLinkHashByRow($row)
    {
        if (array_key_exists('_partner_link_hash', $row)) {
            return $row['_partner_link_hash'];
        }

        return md5($this->normalizePartnerLink($this->getPartnerLinkByRow($row)));
    }

    protected function getEntityByRow($row)
    {
        if (isset($this->mappings['entity'])) {
            $map = $this->mappings['entity'];
            $value = trim($row[$this->indexes[$this->mappings['entity']['column']]]);

            if (array_key_exists('modify', $map) && array_key_exists($value, $modifies = $map['modify']) && strlen($modifies[$value]['value'])) {
                $value = $modifies[$value]['value'];
            }

            return $value;
        }

        return '';
    }

    protected function getPartnerUpdatedAtByRow($row)
    {
        if (array_key_exists('_partner_updated_at', $row)) {
            return $row['_partner_updated_at'];
        }

        return isset($row[$this->indexes[$this->mappings['partner_updated_at']['column']]])
            ? (int)trim($row[$this->indexes[$this->mappings['partner_updated_at']['column']]])
            : $this->microtime;
    }

    # @todo
    protected function insertItems()
    {
        try {
            $this->log('-----------PROGRESS-----------')
                ->log('PASSED = ' . $this->passed)
                ->log('SKIPPED by unique key = ' . $this->skippedByUnique)
                ->log('SKIPPED by updated at = ' . $this->skippedByUpdated)
                ->log('SKIPPED by other = ' . $this->skippedByOther)
                ->log('SKIPPED = ' . ($this->skippedByOther + $this->skippedByUnique + $this->skippedByUpdated))
                ->log('------------------------------');

            if ((!$this->keys) || (!$this->bindValues) || (!$this->index)) {
                return false;
            }

            $columnsOptions = $this->app->managers->items->getEntity()->getColumns();

            $tmp = [];

            for ($i = 0, $s = count($this->keys); $i < $this->index; $i++) {
                $tmp[] = implode(',', array_fill(0, $s, '?'));
            }

            $onDuplicateIgnoreColumns = [
                $this->app->managers->items->getEntity()->getPk(),
                $this->app->managers->vendors->getEntity()->getPk(),
                $this->app->managers->sources->getEntity()->getPk(),
                'partner_item_id',
//                'image',
                'created_at',
                'updated_at'
            ];

            $editableColumns = $this->isForceUpdate ? [] : self::getPostEditableColumns();

            $db = $this->app->storage->mysql;

            $onDuplicateClosure = function ($column, $value) use ($db) {
                return $db->quote($column) . ' = ' . $value;
            };

            $query = new Query;
            $query->text = implode(' ', [
                'INSERT INTO',
                $db->quote($this->app->managers->items->getEntity()->getTable()),
                '(' . implode(', ', array_map(function ($i) use ($db) {
                    return $db->quote($i);
                }, $this->keys)) . ') VALUES ' . implode(', ', array_map(function ($i) {
                    return '(' . $i . ')';
                }, $tmp)),
                'ON DUPLICATE KEY UPDATE',

                //@todo next code overwrites all items fixes... (+add fixWhere clauses maybe?! +take into account "updated_at" in this query)

                implode(', ', array_map(function ($column) use ($onDuplicateClosure, $db) {
                    return $onDuplicateClosure($column, 'VALUES(' . $db->quote($column) . ')');
                }, array_diff($this->keys, $onDuplicateIgnoreColumns, $editableColumns))) . ',',

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
                }, $editableColumns)) . ($editableColumns ? ',' : ''),
                $onDuplicateClosure('updated_at', 'NOW()')
            ]);
            $query->params = $this->bindValues;
            $query->log = $this->debug;

            $aff = $db->req($query)->affectedRows();

            $this->log('AFF item: ' . (int)$aff);
            return $aff;
        } catch (\Exception $ex) {
            $this->app->services->logger->makeException($ex);
            return false;
        }
    }

    public static function factoryAndRun(App $app, ImportSource $importSource = null, bool $force = false, bool $safe = true)
    {
        /** @var Web|Console $app */

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
            } catch (\Throwable $ex) {
                $app->services->logger->makeException($ex);
                $app->services->logger->make('Import (import source id = ' . $importSource->getId() . ') failed!');
            }
        }

        return true;
    }

    protected function getHash(): string
    {
        return md5(implode('', [
            (new Script($this->getDownloadedCsvFileName()))->getUniqueHash(),
            md5(implode('', [
                $this->getFilename(),
                $this->source->getFileFilter(),
                $this->source->getFileMapping()
            ]))
        ]));
    }

    /**
     * @var ImportHistory
     */
    protected $history;

    protected function createHistory(string $hash)
    {
        $this->history = (new ImportHistory)
            ->setImportSourceId($this->source->getId())
            ->setError('unknown error')
            ->setHash($hash);

        $this->app->managers->importHistory->insertOne($this->history);

        $this->log('history id: ' . $this->history->getId());

        return $this;
    }

    protected function updateHistory()
    {
        if ($this->history && $this->history->getId()) {
            $this->app->managers->importHistory->updateOne($this->history
                ->setCountTotal($this->walkTotal)
                ->setCountFilteredFilter($this->walkFilteredFilter)
                ->setCountFilteredModifier($this->walkFilteredModifier)
                ->setCountSkippedUnique($this->skippedByUnique)
                ->setCountSkippedUpdated($this->skippedByUpdated)
                ->setCountSkippedOther($this->skippedByOther)
                ->setCountPassed($this->passed)
                ->setCountAffected($this->aff)
                ->setError($this->error));
        } else {
            $this->log('invalid history object');
        }

        return $this;
    }

    /**
     * @param $category
     * @param $key
     * @param $partnerItemId
     * @param $groups
     */
    protected function addToGroup($category, $key, $partnerItemId, &$groups)
    {
        if (!isset($groups[$category])) {
            $groups[$category] = [];
        }

        if (isset($groups[$category][$key])) {
            if (is_array($groups[$category][$key])) {
                if (!in_array($partnerItemId, $groups[$category][$key])) {
                    $groups[$category][$key][] = $partnerItemId;
                }
            } else {
                if ($groups[$category][$key] != $partnerItemId) {
                    $groups[$category][$key] = [$groups[$category][$key], $partnerItemId];
                }
            }
        } else {
            $groups[$category][$key] = $partnerItemId;
        }
    }

    protected function filterGroups(array &$groups)
    {
        foreach ($groups as $category => $keyToPartnerItemId) {
            $groups[$category] = array_filter($groups[$category], function ($partnerItemId) {
                return is_array($partnerItemId);
            });
        }
    }

    /**
     * @param array $row
     *
     * @return array
     */
    protected function getRowDuplicates($row)
    {
        $category = $this->getCategoryByRow($row);
        $link = $this->getPartnerLinkHashByRow($row);

//        if (isset($this->linkGroups[$category][$link])) {
//            return $this->linkGroups[$category][$link];
//        }

        if (isset($this->linkGroups[$category][$link]) && is_array($this->linkGroups[$category][$link])) {
            return $this->linkGroups[$category][$link];
        }

        return false;
    }

    protected function getRowGarbage($row)
    {
        $category = $this->getCategoryByRow($row);
        $image = $this->getImagesByRow($row)[0];

//        if (isset($this->imageGroups[$category][$image])) {
//            return $this->imageGroups[$category][$image];
//        }

        if (isset($this->imageGroups[$category][$image]) && is_array($this->imageGroups[$category][$image])) {
            return $this->imageGroups[$category][$image];
        }

        return false;
    }

    protected function dropRowGarbage($row)
    {
        $category = $this->getCategoryByRow($row);
        $image = $this->getImagesByRow($row)[0];

        unset($this->imageGroups[$category][$image]);
    }

    /**
     * Collects existing (in db; already imported) rows patner item ids and theirs duplicates (if has) patner item ids
     *
     * @param array $rows
     *
     * @return array
     */
    protected function collectExistingPartnerItemId(array $rows): array
    {
        $output = [];

        foreach ($rows as $partnerItemId => $row) {
            if ($duplicates = $this->getRowDuplicates($row)) {
                foreach ($duplicates as $duplicatePartnerItemId) {
                    $output[] = $duplicatePartnerItemId;
                }
            } else {
                $output[] = $partnerItemId;
            }
        }

        return $this->getPartnerItemIdByPartnerItemId($output);
    }

    /**
     * Collects existing (in db; already imported) rows images and theirs duplicates (if has) images
     *
     * @param array $rows
     *
     * @return array
     */
    protected function collectExistingImages(array $rows): array
    {
        $output = [];

        $images = [];

        foreach ($rows as $row) {
            if ($duplicates = $this->getRowDuplicates($row)) {
                foreach ($duplicates as $duplicatePartnerItemId) {
                    if (isset($this->fileRows[$duplicatePartnerItemId])) {
                        foreach ($this->getImagesByRow($this->fileRows[$duplicatePartnerItemId]) as $image) {
                            $images[] = $image;
                        }
                    }
                }
            } else {
                foreach ($this->getImagesByRow($row) as $image) {
                    $images[] = $image;
                }
            }
        }

        foreach ($this->app->managers->items->clear()
                     ->setColumns(['partner_item_id', 'image'])
                     ->setWhere(['import_source_id' => $this->source->getId(), 'image' => $images])
                     ->setQueryParam('log', $this->debug)
                     ->getItems() as $v) {
            $output[] = $v['image'];
        }

        $images = array_diff($images, $output);

        $db = $this->app->storage->mysql;

        $query = new Query(['params' => []]);
        $query->text = implode(' ', [
            $db->makeSelectSQL(['partner_item_id', 'image_id'], false, $query->params),
            $db->makeFromSQL($this->app->managers->items->getEntity()->getTable()),
            $db->makeJoinSQL([[
                $this->app->managers->items->getEntity()->getTable(),
                $this->app->managers->itemImages->getEntity()->getTable(),
                $this->app->managers->items->getEntity()->getPk()
            ]], $query->params),
            $db->makeWhereSQL(['import_source_id' => $this->source->getId(), 'image_id' => $images], $query->params)
        ]);

        foreach ($db->req($query)->reqToArrays() as $row) {
            $output[] = $row['image_id'];
        }

        return $output;
    }

    protected $downloadImages;

    protected $skippedAsDuplicate;
    protected $skippedAsGarbage;
//    protected $existingPartnerItemId;
    protected $existingImages;

    protected $linkGroups;
    protected $imageGroups;

    protected $dbRows;
    protected $fileRows;

    /**
     * @todo ensure ids only
     * @todo what if categories were updated after first import (e.g. db items has another categories now)?? - could
     * be resolved if
     * // 1) take into account existing items
     * // 2) load whole file (no updated_at)
     *
     * @todo check & fix:
     *       1) source + id - one single unique pair
     *       2) if item exists - do not check image existence - compare hashes only, and if not equal -
     *       update hash & download
     *
     * @todo big improvements:
     *       main components:
     *          partner_item_id
     *          partner_link
     *          image
     *
     * @param bool $downloadImages
     * @param \Closure $onAdd
     * @param \Closure|null $onEnd
     *
     * @return bool
     */
    public function walkImport(bool $downloadImages, \Closure $onAdd, \Closure $onEnd = null)
    {
        try {
            $this->downloadImages = $downloadImages;

            $this->before();

            $this->microtime = (int)microtime(true);

            $this->isRuLang = in_array('ru', $this->langs);

            $this->images = [];

            $this->prepareSva();
            $this->prepareMva();

            $this->skippedByUnique = 0;
            $this->skippedByUpdated = 0;
            $this->skippedByOther = 0;

            $checkUpdatedAt = isset($this->mappings['partner_updated_at']['column']);

            $this->linkGroups = [];
            $this->imageGroups = [];

            $linkCategories = [];

            # db
            $this->dbRows = [];

            foreach ($this->getDbItems() as $row) {
                $partnerItemId = $row['partner_item_id'];
                $category = $row['category_id'];
                $link = $row['partner_link_hash'];
                $image = $row['image'];

                # partner_item_id
                $this->dbRows[$partnerItemId] = $row;

                # duplicates:partner_link_hash
                $this->addToGroup($category, $link, $partnerItemId, $this->linkGroups);

                # garbage:image
                $this->addToGroup($category, $image, $partnerItemId, $this->imageGroups);

                if (!isset($linkCategories[$link])) {
                    $linkCategories[$link] = [];
                }

                $linkCategories[$link][] = $category;
            }

            $categoryChildren = $this->app->managers->categoriesToChildren->getGroupedArrays();

            # file
            $this->fileRows = [];

            $this->walkFilteredFile(function ($row, $i) use ($linkCategories, $categoryChildren) {
                if (!$partnerItemId = $this->getPartnerItemIdByRow($row)) {
                    return true;
                }

                $row['_partner_item_id'] = $partnerItemId;

                if (!$category = $this->getCategoryByRow($row)) {
                    return true;
                }

                if (!$rawLink = $this->getPartnerLinkByRow($row)) {
                    return true;
                }

                $row['_partner_link'] = $rawLink;

                if (!$link = $this->getPartnerLinkHashByRow($row)) {
                    return true;
                }

                $row['_partner_link_hash'] = $link;

                if (!$images = $this->getImagesByRow($row)) {
                    return true;
                }

                if (!!$images[0]) {
                    return true;
                }

                $row['_images'] = $images;

                # todo change category if duplicate already exists in different one

                if (isset($linkCategories[$link]) && isset($categoryChildren[$category])) {
                    foreach ($linkCategories[$link] as $linkCategory) {
                        if (in_array($linkCategory, $categoryChildren[$category])) {
                            $category = $linkCategory;
                        }
                    }
                }

                $row['_category_id'] = $category;

                # todo improve logic (separate table)
                # todo what if already saved image is not first?

                # partner_item_id
                $this->fileRows[$partnerItemId] = $row;

                # duplicates:partner_link_hash
                $this->addToGroup($category, $link, $partnerItemId, $this->linkGroups);

                # garbage:image
                $this->addToGroup($category, $images[0], $partnerItemId, $this->imageGroups);
            });

//            $this->filterGroups($this->linkGroups);
//            $this->filterGroups($this->imageGroups);


            $this->skippedAsDuplicate = [];
            $this->skippedAsGarbage = [];

            foreach (array_chunk($this->fileRows, self::LIMIT, true) as $rows) {
                # @todo fill inserts, updates and deletes

                $this->existingPartnerItemId = $this->collectExistingPartnerItemId($rows);

                if ($this->downloadImages) {
                    $this->existingImages = $this->collectExistingImages($rows);
                }

                if ($checkUpdatedAt) {
                    $partnerItemIdToPartnerUpdatedAt = $this->getPartnerUpdatedAtByPartnerItemId(array_keys($rows));
                } else {
                    $partnerItemIdToPartnerUpdatedAt = [];
                }

                foreach ($rows as $partnerItemId => $row) {
                    $link = $this->getPartnerLinkHashByRow($row);

                    if (in_array($partnerItemId, $this->skippedAsDuplicate)) {
                        $this->log(implode(' ', [
                            '[SKIPPED as duplicate]',
                            'partner_id=' . $partnerItemId,
                            'link=' . $link
                        ]));
                        continue;
                    }

                    if (in_array($partnerItemId, $this->skippedAsGarbage)) {
                        $row['_price'] = $this->getPriceByRow($row);
                        $row['_name'] = $this->getNameByRow($row);

                        $this->log(implode(' ', [
                            '[SKIPPED as garbage]',
                            'partner_id=' . $partnerItemId,
                            'image=' . $row['_images'][0],
                            'name=' . $row['_name'],
                            'price=' . $row['_price'],
                            'link=' . $link
                        ]));
                        continue;
                    }

                    # manage duplicates
                    if ($duplicates = $this->getRowDuplicates($row)) {
                        $this->log('[' . $partnerItemId . '] duplicates: ' . implode(', ', $duplicates));


                        if (in_array($partnerItemId, $this->existingPartnerItemId)) {
                            $mainPartnerItemId = $partnerItemId;
                        } else {
                            $mainPartnerItemId = null;

                            foreach ($duplicates as $duplicatePartnerItemId) {
                                if (in_array($duplicatePartnerItemId, $this->existingPartnerItemId)) {
                                    $mainPartnerItemId = $duplicatePartnerItemId;
                                    break;
                                }
                            }

                            if (null === $mainPartnerItemId) {
                                $mainPartnerItemId = $partnerItemId;
                            }
                        }

//                        $this->log('partner item id: ' . $partnerItemId);
//                        $this->log('duplicates: ' . var_export($duplicates, true));
//                        $this->log('main partner item id: ' . $mainPartnerItemId);

                        foreach ($duplicates as $duplicatePartnerItemId) {
                            if (($mainPartnerItemId != $duplicatePartnerItemId) && isset($this->fileRows[$duplicatePartnerItemId])) {
                                $this->skippedAsDuplicate[] = $duplicatePartnerItemId;

                                $this->rememberAllMvaByRow($this->fileRows[$duplicatePartnerItemId], $mainPartnerItemId);
                                $this->rememberManualTagsByRow($this->fileRows[$duplicatePartnerItemId], $mainPartnerItemId);
                                # todo check if need download (what if already downloaded)
                                $this->rememberDownloadedImagesByRow($this->fileRows[$duplicatePartnerItemId], $mainPartnerItemId);
                            }
                        }

                        # ignore garbage if all they are duplicates
                        if (($garbage = $this->getRowGarbage($row)) && !array_diff($duplicates, $garbage)) {
                            $this->dropRowGarbage($row);
                        }

//                        $this->log('partner item id: ' . $partnerItemId);
//                        $this->log('garbage: ' . var_export($this->getRowGarbage($row), true));
                    }

                    # manage garbage
                    if ($garbage = $this->getRowGarbage($row)) {
                        # todo compare prices and if so - merge them as duplicates
                        foreach ($garbage as $garbagePartnerItemId) {
                            $this->skippedAsGarbage[] = $garbagePartnerItemId;
                        }

                        continue;
                    }

                    # skip updated
                    if (isset($partnerItemIdToPartnerUpdatedAt[$partnerItemId])) {
                        $row['_partner_updated_at'] = $this->getPartnerUpdatedAtByRow($row);

                        if (!$this->isForceUpdate && ($partnerItemIdToPartnerUpdatedAt[$partnerItemId] <= $row['_partner_updated_at'])) {
                            $this->log(implode(' ', [
                                '[SKIPPED by updated_at]',
                                'partner_id=' . $partnerItemId,
                                'updated_at=' . $row['_partner_updated_at']
                            ]));
                            $this->skippedByUpdated++;
                            continue;
                        }
                    }

                    if ($values = $this->rememberRow($row)) {
                        if (false === $onAdd($row, $values)) {
                            break 2;
                        }
                    } else {
//                        $partnerItemId = $this->getPartnerItemIdByRow($row);

                        foreach ($this->mva as $pk => $data) {
                            if (isset($data['values'][$partnerItemId])) {
                                unset($this->mva[$pk]['values'][$partnerItemId]);
                            }
                        }

                        if (isset($this->images[$partnerItemId])) {
                            unset($this->images[$partnerItemId]);
                        }

                        $this->skippedByOther++;
                    }

//                    $this->log('partner item id: ' . $partnerItemId);
//                    $this->log('values: ' . var_export($values, true));
                }
            }

            $this->skippedByUnique = count($this->skippedAsDuplicate) + count($this->skippedAsGarbage);

            $onEnd && $onEnd();

            return true;
        } catch (\Throwable $ex) {
            $this->app->services->logger->makeException($ex);
            $this->error = $ex->getTraceAsString();
            return false;
        }
    }

    protected function beforeRow($row)
    {

    }

    protected function rememberRow($row)
    {
        $this->sport = null;
        $this->sizePlus = null;

        $ok = false;

        $values = [];

        while (true) {
            $this->beforeRow($row);

            if (!$values['partner_item_id'] = $this->getPartnerItemIdByRow($row)) {
                break;
            }

            $values = array_merge($values, $this->getAllSvaByRow($row));

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

            if (!$values['partner_link'] = $this->getPartnerLinkByRow($row)) {
                $this->log('[SKIPPED link] partner_id=' . $values['partner_item_id']);
                break;
            }

            # @todo optimize
            $values['partner_link_hash'] = $this->getPartnerLinkHashByRow($row);

            if (!$values['image'] = $this->getDownloadedImageByRow($row)) {
                $this->log('[SKIPPED image] partner_id=' . $values['partner_item_id']);
                break;
            }

            $values['import_source_id'] = $this->getImportSourceByRow($row);
            $values['old_price'] = $this->getOldPriceByRow($row);
            $values['is_in_stock'] = $this->getIsInStockByRow($row);
            $values['entity'] = $this->getEntityByRow($row);
            $values['is_sport'] = $this->sport ? 1 : 0;
            $values['is_size_plus'] = $this->sizePlus ? 1 : 0;
            $values['partner_updated_at'] = $this->getPartnerUpdatedAtByRow($row);

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

            $this->rememberAllMvaByRow($row);

            $ok = true;

            break;
        }

        if ($ok) {
            return $values;
        }

        return false;
    }

    protected $lastOkImport;

    protected function getLastOkImport(): ?ImportHistory
    {
        if (null === $this->lastOkImport) {
            $tmp = $this->app->managers->importHistory
                ->setWhere([
                    'import_source_id' => $this->source->getId(),
                    'error' => null
                ])
                ->setOrders(['import_history_id' => SORT_DESC])
                ->getObject();

            $this->lastOkImport = null === $tmp ? false : $tmp;
        }

        return false === $this->lastOkImport ? null : $this->lastOkImport;
    }

    protected function getFileLastModifiedTime(): ?DateTime
    {
        return FileSystem::getRemoteFileLastModifiedTime($this->getFilename());
    }

    protected function check(): self
    {
        if (!$this->app->managers->sources->getVendor($this->source)->isActive()) {
            throw new \Exception('vendor [import_source_id=' . $this->source->getName() . '] is disabled');
        }

        if ($this->app->request->isCli() && !$this->source->isCron()) {
            throw new \Exception('[import_source_id=' . $this->source->getName() . '] is out of cron');
        }

        return $this;
    }

    protected function getPidFilename(): string
    {
        return implode('/', [
            $this->app->dirs['@tmp'],
            implode('_', [
                'import_pid',
                $this->source->getId()
            ])
        ]);
    }

    protected function checkPid(): bool
    {
        return FileSystem::isFileExists($this->getPidFilename());
    }

    protected function createPid(): bool
    {
        $file = $this->getPidFilename();

        $output = FileSystem::createFile($file, $this->source->getId());

        if ($output) {
            $this->setAccessPermissions($file);
        }

        return $output;
    }

    protected function deletePid(): bool
    {
        return FileSystem::deleteFile($this->getPidFilename());
    }

    public function run(): ?int
    {
        $this->check();

        if ($this->checkPid()) {
            $this->log('SKIPPED by previous running');
            return null;
        }

        $this->createPid();

        try {
            $hash = $this->getHash();

            $history = $this->getLastOkImport();

            if ($history && $history->getHash() == $hash) {
                # @todo restore
//                $this->log('SKIPPED by hash');
//                return null;
            }

            $this->createHistory($hash);

            //@todo this is wrong logic (coz of pre-import skips)
            //@todo replace with: update for non existing partner_item_id in file (but exists in db), after import
//            $this->setOutOfStock();

            $fixWhere = (new FixWhere($this->app))
                ->setSources([$this->source])
                //@todo replace with last id
                ->setCreatedAtFrom($ts = time() - 1)
                ->setOrBetweenCreatedAndUpdated(true)
                ->setUpdatedAtFrom($ts);

            $this->aff = 0;

            $this->itemColumns = $this->getItemColumns();
            $this->requiredColumns = $this->getRequiredItemColumns();

            $this->index = 0;
            $this->bindValues = [];
            $this->passed = 0;

            $this->walkImport(true, function ($row, $values) {
                $this->log('[PASSED] partner_id=' . $values['partner_item_id']);

                if (!isset($this->keys)) {
                    $this->keys = array_keys($values);
                }

                foreach ($values as $v) {
                    $this->bindValues[] = $v;
                }

                $this->passed++;
                $this->index++;

//                $this->log('index: ' . $this->index);

                if (self::LIMIT == $this->index) {
                    $this->aff += $this->insertItems();
                    $this->index = 0;
                    $this->bindValues = [];
                }
            }, function () {
                if ($this->index) {
                    $this->aff += $this->insertItems();
                }

                $this->insertImages();
                $this->insertMva();
            });

            $this->updateHistory();

            if ($this->aff) {
                $this->app->utils->items->doFixWithNonExistingAttrs($fixWhere);

                $aff = $this->app->utils->attrs->doDeleteNonExistingItemsMva($fixWhere);
                $this->log('updated with invalid mva: ' . $aff);

//                $this->app->utils->attrs->doAddMvaByInclusions($fixWhere);
                $this->app->utils->items->doFixItemsCategories($fixWhere);
            }

            $this->deleteOldFiles();
        } catch (\Throwable $ex) {
            $this->app->services->logger->makeException($ex);
        }

        $this->deletePid();

        return $this->aff;
    }

    public function __invoke()
    {
        return $this->run();
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
