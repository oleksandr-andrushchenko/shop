<?php

namespace SNOWGIRL_SHOP;

use Generator;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Helper\Strings;
use SNOWGIRL_CORE\Helper\FileSystem;
use SNOWGIRL_CORE\Mysql\MysqlQuery;
use SNOWGIRL_CORE\AbstractApp as App;
use SNOWGIRL_CORE\Mysql\MysqlQueryExpression;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_SHOP\Entity\Import\History as ImportHistory;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttr;
use SNOWGIRL_SHOP\Http\HttpApp;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;
use SNOWGIRL_SHOP\Entity\Country;
use SNOWGIRL_SHOP\Item\FixWhere;
use stdClass;
use Throwable;

/**
 * Class Import (for ImportSource::TYPE_PARTNER)
 * @todo use compose instead of extend
 * @package SNOWGIRL_SHOP
 */
class Import
{
    protected const LIMIT = 500;

    /**
     * @var HttpApp|ConsoleApp
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
    protected $cacheDropped;
    protected $walkTotal;
    protected $walkFilteredFilter;
    protected $walkFilteredModifier;
    protected $walkFileSize;
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
    protected $sva;

    /**
     * Partner_item_id => image_with_dimensions[]
     * @var array
     */
    protected $images;
    protected $mva;
    /**
     * @var ImportHistory
     */
    protected $history;
    protected $downloadImages;

    protected $skippedAsDuplicate;
    protected $skippedAsGarbage;
    protected $existingPartnerItemId;
    protected $existingImages;

    protected $linkGroups;
    protected $imageGroups;

    protected $dbRows;
    protected $fileRows;

    /**
     * @var ImportHistory
     */
    protected $lastOkImport;

    protected $inStockFilePartnerItemId;

    private $linkCategories;
    private $categoryChildren;
    private $partnerItemIdToPartnerUpdatedAt;
    private $countOutOfStock;

    /**
     * @var bool
     */
    private $forceOutOfStock;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $debug;
    private $profile;
    private $profileData;

    /**
     * Import constructor.
     * @param App $app
     * @param ImportSource $source
     * @param bool|null $debug
     * @param bool $profile
     * @throws Exception
     */
    public function __construct(App $app, ImportSource $source, bool $debug = null, bool $profile = false)
    {
        $this->app = $app;
        $this->source = $source;

        $this->logger = clone $app->container->logger;

        $this->logger->pushProcessor(function ($data) {
            $data['extra']['source_id'] = $this->source->getId();

            return $data;
        });

        $this->initMeta();

        $this->filters = $this->getFilters();
        $this->mappings = $this->getMappings();

        $this->forceOutOfStock = $this->app->managers->sources->getVendor($source)->isFake();

        if ($this->profile = $profile) {
            foreach ($this->logger->getHandlers() as $handler) {
                $handler->setLevel(Logger::WARNING);
            }

            $handler = new StreamHandler('php://stdout', Logger::INFO);
            $handler->setFormatter($app->container->makeSingle('logger_handler_formatter', 'logger'));
            $this->logger->pushHandler($handler);

            $this->profileData = [];
        }

        $this->debug = $debug;
    }

    public function __destruct()
    {
        if ($this->profile && count($this->profileData)) {
            $filePointer = fopen(implode('/', [
                $this->app->dirs['@tmp'],
                implode('_', [
                    'import_profiler',
                    $this->source->getId(),
                    date('Y_m_d'),
                    time() . '.csv',
                ]),
            ]), 'w');

            if ($filePointer) {
                fputcsv($filePointer, [
                    'metric',
                    'count',
                    'time_sum',
                    'time_min',
                    'time_max',
                    'time_avg',
                    'memory_sum',
                    'memory_min',
                    'memory_max',
                    'memory_avg',
                ]);

                $data = [];

                foreach ($this->profileData as $metric => $values) {
                    $data[] = [
                        $metric,
                        $count = count($values['time']),

                        round($sum = array_sum($values['time']), 2),
                        round(min($values['time']), 2),
                        round(max($values['time']), 2),
                        round($sum / $count, 2),

                        round($sum = array_sum($values['memory']), 2),
                        round(min($values['memory']), 2),
                        round(max($values['memory']), 2),
                        round($sum / $count, 2),
                    ];
                }

                usort($data, function ($a, $b) {
                    return $b[2] - $a[2];
                });

                foreach ($data as $row) {
                    fputcsv($filePointer, $row);
                }

                fclose($filePointer);
            }
        }
    }

    public function getFilename(): string
    {
        return $this->source->getFile();
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getMeta()
    {
        $output = [
            'columns' => [],
            'indexes' => [],
        ];

        if ($filePointer = fopen($this->getDownloadedCsvFileName(), 'r')) {
            $output['columns'] = explode($this->csvFileDelimiter, rtrim(fgets($filePointer)));
            fclose($filePointer);
        }

        $output['indexes'] = array_combine($output['columns'], range(0, count($output['columns']) - 1));

        return $output;
    }

    /**
     * @param bool $history
     * @return bool
     * @throws Exception
     */
    public function dropCache($history = false): bool
    {
        if (!$this->cacheDropped) {
            $this->logger->debug('dropping cache...');

            if ($this->checkPid()) {
                throw new Exception('previous running...');
            }

            try {
                FileSystem::deleteFilesByPattern($this->getCsvFilename(true));

                if ($history) {
                    $this->app->managers->importHistory->deleteMany(['import_source_id' => $this->source->getId()]);
                }

                $this->history = null;
                $this->initMeta();

                $this->cacheDropped = true;
            } catch (Throwable $e) {
                $this->logger->error($e);
                return false;
            }
        }

        return $this->cacheDropped;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getDownloadedCsvFileName(): string
    {
        $file = $this->getCsvFilename();

        if (!FileSystem::isFileExists($file)) {
            $this->logger->debug('downloading file...');

            shell_exec(implode(' ', [
                '/usr/bin/wget --quiet',
                '--output-document="' . $file . '"',
                '"' . $this->getFilename() . '"',
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
            'application/txt',
        ])) {
            throw new Exception('invalid csv file "' . $file . '"');
        }

        return $file;
    }

    /**
     * This could imply on ::updateMissedAsOutOfStock
     * @param bool|null $allowModifyOnlyCheck
     * @param array $allowModifyOnlyExclude
     * @return Generator
     * @throws Exception
     */
    public function getFilteredFile(bool $allowModifyOnlyCheck = null, array $allowModifyOnlyExclude = []): Generator
    {
        try {
            if (null === $allowModifyOnlyCheck) {
                $allowModifyOnlyCheck = $this->defaultAllowModifyOnly;
            }

            $this->walkTotal = 0;
            $this->walkFilteredFilter = 0;
            $this->walkFilteredModifier = 0;

            $this->walkFileSize = count($this->columns);

            if (!$filePointer = fopen($file = $this->getDownloadedCsvFileName(), 'r')) {
                throw new Exception('invalid file: ' . $file);
            }

            //skip first line (columns)
            fgets($filePointer);


            while ($row = self::readFileRow($filePointer, $this->csvFileDelimiter)) {
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

                $row = $this->beforeReadRow($row);

                yield $row;
            }
        } finally {
            if ($filePointer) {
                fclose($filePointer);
            }
        }
    }

    /**
     * @param int $page
     * @param int $size
     * @return stdClass
     * @throws Exception
     */
    public function getData(int $page = 1, int $size = 10): stdClass
    {
        $return = new stdClass();

        $return->columns = $this->columns;
        $return->indexes = $this->indexes;

        $rows = [];
        $startIndex = ($page - 1) * $size;
        $endIndex = $page * $size - 1;

        foreach ($this->getFilteredFile() as $i => $row) {
            if ($i < $startIndex) {
                continue;
            }

            if ($i > $endIndex) {
                break;
            }

            $rows[] = $row;
        }

        $return->data = $rows;
        $return->totalItems = $this->walkTotal;
        $return->totalPages = ceil($return->totalItems / $size);

        return $return;
    }

    /**
     * @param array $columns
     * @param bool $allowModifyOnly
     * @param array $allowModifyOnlyExclude
     * @return array
     * @throws Exception
     */
    public function getFileColumnsValuesInfo(array $columns, bool $allowModifyOnly = true, array $allowModifyOnlyExclude = [])
    {
        $output = array_combine($columns, array_fill(0, count($columns), []));

        foreach ($this->getFilteredFile($allowModifyOnly, $allowModifyOnlyExclude) as $row) {
            foreach ($columns as $column) {
                $v = $row[$this->indexes[$column]];

                if (!isset($output[$column][$v])) {
                    $output[$column][$v] = [
                        'total' => 0,
                        'items' => [],
                    ];
                }

                $output[$column][$v]['total']++;

                if ($output[$column][$v]['total'] < 6) {
                    $output[$column][$v]['items'][] = $row;
                }
            }
        }

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
                        'name' => isset($nameIndex) && isset($item[$nameIndex]) ? $item[$nameIndex] : null,
                    ];
                }
            }
        }

        return $output;
    }

    /**
     * @param $counts
     * @return array
     * @throws Exception
     */
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

    /**
     * @param $column
     * @return mixed
     * @throws Exception
     */
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

//            'order_desc_relevance',
//            'order_desc_rating',
//            'order_asc_price',
//            'order_desc_price',

            'partner_link_hash',

            'created_at',
            'updated_at',
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
            $this->app->managers->sources->getEntity()->getPk(),
        ]);
    }

    public static function getSvaValues(App $app)
    {
        return [
            Category::getPk() => $app->utils->attrs->getIdToName(Category::class),
//            Brand::getPk() => $app->utils->attrs->getIdToName(Brand::class),
            Country::getPk() => $app->utils->attrs->getIdToName(Country::class),
        ];
    }

    public static function factoryAndRun(App $app, ImportSource $importSource = null, bool $debug = null, bool $profile = false): ?bool
    {
        /** @var HttpApp|ConsoleApp $app */


        $where = ['is_cron' => 1];

        if ($importSource) {
            $where['import_source_id'] = $importSource->getId();
        }

        /** @var ImportSource[] $importSources */
        $importSources = $app->managers->sources
            ->setWhere($where)
            ->setOrders([ImportSource::getPk() => SORT_ASC])
            ->getObjects();

        if (!count($importSources)) {
            $app->container->logger->debug('None of cron sources were found');

            return null;
        }

        foreach ($importSources as $importSource) {
            try {
                $app->managers->sources->getImport($importSource, $debug, $profile)->run();
            } catch (Throwable $e) {
                $app->container->logger->error($e);
                $app->container->logger->debug('import failed!');
            }
        }

        return true;
    }

    /**
     * @todo ensure ids only
     * @todo what if categories were updated after first import (e.g. db items has another categories now)?? - could
     * be resolved if
     * // 1) take into account existing items
     * // 2) load whole file (no updated_at)
     * @todo check & fix:
     *       1) source + id - one single unique pair
     *       2) if item exists - do not check image existence - compare hashes only, and if not equal -
     *       update hash & download
     * @todo big improvements:
     *       main components:
     *          partner_item_id
     *          partner_link
     *          image
     * @param callable $onAdd
     * @param callable|null $onEnd
     * @param bool $downloadImages
     * @return bool
     */
    public function walkImport(bool $downloadImages, callable $onAdd, callable $onEnd = null)
    {
        try {
            $this->downloadImages = $downloadImages;
            $this->microtime = (int) microtime(true);
            $this->isRuLang = in_array('ru', $this->langs);
            $this->images = [];

            $this->profile('prepareSva', function () {
                $this->prepareSva();
            });

            $this->profile('prepareMva', function () {
                $this->prepareMva();
            });

            $this->sport = [];
            $this->sizePlus = [];

            $this->skippedByUnique = 0;
            $this->skippedByUpdated = 0;
            $this->skippedByOther = 0;

            $checkUpdatedAt = isset($this->mappings['partner_updated_at']['column']);

            $this->linkGroups = [];
            $this->imageGroups = [];

            $this->linkCategories = [];
            $this->categoryChildren = [];

            $this->inStockFilePartnerItemId = [];

            # db
            $this->dbRows = [];


            $this->profile('beforeWalkImport', function () {
                $this->beforeWalkImport();
            });


            $this->profile('dbCollections', function () {

                foreach ($this->profile('getDbItems', function () {
                    return $this->getDbItems();
                }) as $row) {
                    $partnerItemId = $row['partner_item_id'];
                    $category = $row['category_id'];
                    $link = $row['partner_link_hash'];
                    $image = $row['image'];

                    # partner_item_id
                    $this->dbRows[$partnerItemId] = $row;

                    # duplicates:partner_link_hash
                    $this->addToLinkGroups($category, $link, $partnerItemId);

                    # garbage:image
                    $this->addToImageGroups($category, $image, $partnerItemId);

                    if (!isset($linkCategories[$link])) {
                        $this->linkCategories[$link] = [];
                    }

                    $this->linkCategories[$link][] = $category;
                }
            });

            $this->profile('categoryChildren', function () {
                $this->categoryChildren = $this->app->managers->categoriesToChildren->getGroupedArrays(true);
            });

            # file
            $this->fileRows = [];
            //@todo $this->inStockFilePartnerItemId.... checkout filtered file...

            $this->profile('fileCollections', function () {
                foreach ($this->getFilteredFile() as $row) {
                    if (!$partnerItemId = $this->getPartnerItemIdByRow($row)) {
                        $this->logger->warning('invalid partner item id', [
                            'row' => $row,
                        ]);
                        continue;
                    }

                    $row['_partner_item_id'] = $partnerItemId;

                    if (!$category = $this->getCategoryByRow($row)) {
                        $this->logger->warning('invalid category', [
                            'row' => $row,
                        ]);
                        continue;
                    }

                    if (!$rawLink = $this->getPartnerLinkByRow($row)) {
                        $this->logger->warning('invalid partner link', [
                            'row' => $row,
                        ]);
                        continue;
                    }

                    $row['_partner_link'] = $rawLink;

                    if (!$link = $this->getPartnerLinkHashByRow($row)) {
                        $this->logger->warning('invalid partner link hash', [
                            'row' => $row,
                        ]);
                        continue;
                    }

                    $row['_partner_link_hash'] = $link;

                    if (!$images = $this->getImagesByRow($row)) {
                        $this->logger->warning('invalid images', [
                            'row' => $row,
                        ]);
                        continue;
                    }

                    if (!$images[0]) {
                        $this->logger->warning('invalid image', [
                            'row' => $row,
                        ]);
                        continue;
                    }

                    $row['_images'] = $images;

                    # todo change category if duplicate already exists in different one

                    if (isset($this->linkCategories[$link]) && isset($this->categoryChildren[$category])) {
                        foreach ($this->linkCategories[$link] as $linkCategory) {
                            if (in_array($linkCategory, $this->categoryChildren[$category])) {
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
                    $this->addToLinkGroups($category, $link, $partnerItemId);

                    # garbage:image
                    $this->addToImageGroups($category, $images[0], $partnerItemId);
                }
            });


            $this->skippedAsDuplicate = [];
            $this->skippedAsGarbage = [];

            foreach (array_chunk($this->fileRows, self::LIMIT, true) as $rows) {
                # @todo fill inserts, updates and deletes

                $this->profile('rowsExistingPartnerItemId', function () use ($rows) {
                    $this->existingPartnerItemId = $this->collectExistingPartnerItemId($rows);
                });

                if ($this->downloadImages) {
                    $this->profile('rowsExistingImages', function () use ($rows) {
                        $this->existingImages = $this->collectExistingImages($rows);
                    });
                }

                if ($checkUpdatedAt) {
                    $this->profile('rowsPartnerItemIdToPartnerUpdatedAt', function () use ($rows) {
                        $this->partnerItemIdToPartnerUpdatedAt = $this->getPartnerUpdatedAtByPartnerItemId(array_keys($rows));
                    });
                } else {
                    $this->partnerItemIdToPartnerUpdatedAt = [];
                }

                foreach ($rows as $partnerItemId => $row) {
                    $result = $this->profile('rowProcessing', function (string &$metric) use ($partnerItemId, $row, $onAdd) {
                        $link = $this->getPartnerLinkHashByRow($row);

                        if (in_array($partnerItemId, $this->skippedAsDuplicate)) {
                            $metric .= '.skippedAsDuplicate';
                            $this->logger->info(implode(' ', [
                                '[SKIPPED as duplicate]',
                                'partner_id=' . $partnerItemId,
                                'link=' . $link,
                            ]));
                            return true;
                        }

                        if (in_array($partnerItemId, $this->skippedAsGarbage)) {
                            $row['_price'] = $this->getPriceByRow($row);
                            $row['_name'] = $this->getNameByRow($row);

                            $metric .= '.skippedAsGarbage';
                            $this->logger->info(implode(' ', [
                                '[SKIPPED as garbage]',
                                'partner_id=' . $partnerItemId,
                                'image=' . $row['_images'][0],
//                            'name=' . $row['_name'],
//                            'price=' . $row['_price'],
//                            'link=' . $link
                            ]));
                            return true;
                        }

                        # manage duplicates
                        $duplicates = $this->profile('rowGetRowDuplicates', function (&$metric) use ($row) {
                            $result = $this->getRowDuplicates($row);
                            $metric .= count($result) ? '.has' : '.empty';
                            return $result;
                        });

                        if ($duplicates) {
                            $this->profile('rowDuplicatesProcessing', function () use ($row, $partnerItemId, $duplicates) {
                                //                        $this->logger->info('[' . $partnerItemId . '] duplicates: ' . implode(', ', $duplicates));


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

//                        $this->logger->info('partner item id: ' . $partnerItemId);
//                        $this->logger->info('duplicates: ' . var_export($duplicates, true));
//                        $this->logger->info('main partner item id: ' . $mainPartnerItemId);

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

//                        $this->logger->info('partner item id: ' . $partnerItemId);
//                        $this->logger->info('garbage: ' . var_export($this->getRowGarbage($row), true));
                            });
                        }

                        # manage garbage
                        if ($garbage = $this->getRowGarbage($row)) {
                            # todo compare prices and if so - merge them as duplicates
                            foreach ($garbage as $garbagePartnerItemId) {
                                $this->skippedAsGarbage[] = $garbagePartnerItemId;
                            }

                            $metric .= '.skippedAsAddedToGarbage';
                            $this->logger->info(implode(' ', [
                                '[SKIPPED as added to garbage]',
                                'partner_id=' . $partnerItemId,
                                'image=' . $row['_images'][0],
//                            'name=' . $row['_name'],
//                            'price=' . $row['_price'],
//                            'link=' . $link
                            ]));

                            return true;
                        }

                        # skip updated
                        if (isset($this->partnerItemIdToPartnerUpdatedAt[$partnerItemId])) {
                            $row['_partner_updated_at'] = $this->getPartnerUpdatedAtByRow($row);

                            if (!$this->isForceUpdate && ($this->partnerItemIdToPartnerUpdatedAt[$partnerItemId] <= $row['_partner_updated_at'])) {
                                $metric .= '.skippedByUpdatedAt';
                                $this->logger->info(implode(' ', [
                                    '[SKIPPED by updated_at]',
                                    'partner_id=' . $partnerItemId,
                                    'updated_at=' . $row['_partner_updated_at'],
                                ]));
                                $this->skippedByUpdated++;
                                return true;
                            }
                        }

                        if ($values = $this->rememberRow($row)) {
                            $onAddResult = $this->profile('rowOnAddProcessing', function () use ($onAdd, $row, $values) {
                                return $onAdd($row, $values);
                            });

                            if (false === $onAddResult) {
                                return false;
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

//                    $this->logger->info('partner item id: ' . $partnerItemId);
//                    $this->logger->info('values: ' . var_export($values, true));

                        return true;
                    });

                    if (!$result) {
                        break 2;
                    }
                }
            }

            $this->skippedByUnique = count($this->skippedAsDuplicate) + count($this->skippedAsGarbage);

            if ($onEnd) {
                $this->profile('rowOnEndProcessing', function () use ($onEnd) {
                    return $onEnd();
                });
            }

            return true;
        } catch (Throwable $e) {
            $this->logger->error($e);
            $this->error = $e->getTraceAsString();
            return false;
        } finally {
            $this->profile('afterWalkImport', function () {
                $this->afterWalkImport();
            });
        }
    }

    /**
     * @return int|null
     * @throws Exception
     */
    public function run(): ?int
    {
        $this->check();

        if ($this->checkPid()) {
            $this->logger->info('SKIPPED by previous running');
            return null;
        }

        $this->dropCache();
        $this->createPid();

        try {
            $hash = $this->getHash();

            $history = $this->getLastOkImport();

            if ($history && $history->getHash() == $hash) {
                $this->logger->info('SKIPPED by hash');
                return null;
            }

            $this->createHistory($hash);

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
                $this->logger->info('[PASSED] partner_id=' . $values['partner_item_id']);

                if (!isset($this->keys)) {
                    $this->keys = array_keys($values);
                }

                foreach ($values as $v) {
                    $this->bindValues[] = $v;
                }

                $this->passed++;
                $this->index++;

//                $this->logger->info('index: ' . $this->index);

                if (self::LIMIT == $this->index) {
                    if ($aff = $this->insertItems()) {
                        $this->aff += $aff;
                    }

                    $this->index = 0;
                    $this->bindValues = [];
                }
            }, function () {
                if ($this->index) {
                    if ($aff = $this->insertItems()) {
                        $this->aff += $aff;
                    }
                }

                $this->insertImages();
                $this->insertMva();
            });

            if ($this->walkTotal) {
                $this->updateMissedAsOutOfStock();
            }

            $this->updateHistory();

            if (false && $this->aff) {
                $aff = $this->app->utils->items->doFixWithNonExistingAttrs($fixWhere, ['log' => $this->debug]);
                $this->logger->info('affected with non-existing attributes: ' . $aff);

                $aff = $this->app->utils->attrs->doDeleteNonExistingItemsMva($fixWhere, ['log' => $this->debug]);
                $this->logger->info('affected with invalid mva: ' . $aff);

//                $this->app->utils->attrs->doAddMvaByInclusions($fixWhere);
                $this->app->utils->items->doFixItemsCategories($fixWhere, ['log' => $this->debug]);
                $this->logger->info('affected with non-appropriate categories: ' . $aff);
            }

            $this->deleteOldFiles();
        } catch (Throwable $e) {
            $this->logger->error($e);
        }

        $this->deletePid();

        return $this->aff;
    }

    /**
     * @return int|null
     * @throws Exception
     */
    public function __invoke()
    {
        return $this->run();
    }

    public static function getPostEditableColumns()
    {
        return [
            'name',
            Category::getPk(),
            Country::getPk(),
        ];
    }

    public function getItemTargetLink(Item $item)
    {
        return null;
    }

    protected function beforeReadRow($row)
    {
        return $row;
    }

    protected function beforeWalkImport()
    {

    }

    protected function afterWalkImport()
    {

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
                $this->rememberMva($partnerItemId, 'tag_id', $modifies[$value]['tags']);
                $this->sport[$partnerItemId] = in_array('is_sport', $modifies[$value]);
                $this->sizePlus[$partnerItemId] = in_array('is_size_plus', $modifies[$value]);
                return (int) $modifies[$value]['value'];
            }

            return $this->getSvaByRow('category_id', $row);
        } elseif ($map['value']) {
            return (int) $map['value'];
        }

        return null;
    }

    protected function getCountryByRow($row)
    {
        return $this->getSvaByRow('country_id', $row);
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

    /**
     * @return null|ImportHistory
     * @throws \Exception
     */
    protected function getLastOkImport(): ?ImportHistory
    {
        if (null === $this->lastOkImport) {
            $tmp = $this->app->managers->importHistory
                ->setWhere([
                    'import_source_id' => $this->source->getId(),
                    'error' => null,
                ])
                ->setOrders(['import_history_id' => SORT_DESC])
                ->getObject();

            $this->lastOkImport = null === $tmp ? false : $tmp;
        }

        return false === $this->lastOkImport ? null : $this->lastOkImport;
    }

    protected function getPartnerItemIdByRow($row)
    {
        if (array_key_exists('_partner_item_id', $row)) {
            return $row['_partner_item_id'];
        }

        return trim($row[$this->indexes[$this->mappings['partner_item_id']['column']]]);
    }

    protected function rememberMva($partnerItemId, $pk, $value)
    {
        if (isset($this->mva[$pk]['values'][$partnerItemId])) {
            $this->mva[$pk]['values'][$partnerItemId] = array_merge($this->mva[$pk]['values'][$partnerItemId], $value);
        } else {
            $this->mva[$pk]['values'][$partnerItemId] = $value;
        }
    }

    protected function beforeRememberRow($row)
    {

    }

    protected function afterRememberRow($row)
    {

    }

    /**
     * @return Import
     * @throws Exception
     */
    private function initMeta(): Import
    {
        $meta = $this->getMeta();
        $this->indexes = $meta['indexes'];
        $this->columns = $meta['columns'];

        return $this;
    }

    private function getFilters()
    {
        if ($this->filters && !$this->source->getFileFilter(true)) {
            $this->source->setFileFilter($this->filters);
        }

        return $this->source->getFileFilter(true);
    }

    private function getMappings()
    {
        if ($this->mappings && !$this->source->getFileMapping(true)) {
            $this->source->setFileMapping($this->mappings);
        }

        return $this->source->getFileMapping(true);
    }

    private function setAccessPermissions($filename)
    {
        chgrp($filename, $this->app->config('server.web_server_group'));
        chown($filename, $this->app->config('server.web_server_user'));
        FileSystem::chmodRecursive($filename, 0775);

        return $this;
    }

    private function getCsvFilename(bool $all = false): string
    {
        return implode('/', [
            $this->app->dirs['@tmp'],
            implode('_', [
                'import_source',
                $this->source->getId(),
                ($all ? '*' : implode('-', [
                    md5($this->getFilename()),
                    date('Y_m_d'),
                ])) . '.csv',
            ]),
        ]);
    }

    private function deleteOldFiles(): int
    {
        $aff = 0;

        $current = $this->getCsvFilename();

        foreach (glob($this->getCsvFilename(true)) as $file) {
            if ($current != $file) {
                if (FileSystem::deleteFile($file)) {
                    $aff++;
                }
            }
        }

        $this->logger->info('deleted old files: ' . $aff);

        return $aff;
    }

    private function readFileRow($filePointer, $delimiter): array
    {
        //completed values
        $row = [];

        //non-completed parts from quotes - terminated by EON
        $quote = [];

        while (!feof($filePointer)) {
            $line = trim(fgets($filePointer));

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

    private function preNormalizeRow($row)
    {
        return $row;
    }

    private function updateMissedAsOutOfStock(): int
    {
        $dbPartnerItemId = [];

        foreach ($this->app->managers->items
                     ->setColumns(['partner_item_id'])
                     ->setWhere([
                         'import_source_id' => $this->source->getId(),
                         'is_in_stock' => 1,
                     ])
                     ->getItems() as $row) {
            $dbPartnerItemId[] = $row['partner_item_id'];
        }

        $aff = 0;

        /**
         *      db:             in file:    in stock:   to be out:
         *  [   [1 2 3 4 5],    [3, 4],     [5] ] =>    [1, 2]
         *  [   [4 5],          [1, 2, 3],  [5, 2]] =>  [4]
         */

        $missedPartnerItemIdChunks = array_chunk(array_diff(
            $dbPartnerItemId,
            array_keys($this->fileRows),
            array_unique($this->inStockFilePartnerItemId)
        ), self::LIMIT);

        foreach ($missedPartnerItemIdChunks as $missedPartnerItemIdChunk) {
            foreach ($missedPartnerItemIdChunk as $missedPartnerItemId) {
                $aff += $this->app->managers->items->updateMany(['is_in_stock' => 0], [
                    'import_source_id' => $this->source->getId(),
                    'partner_item_id' => $missedPartnerItemId,
                ]);
            }
        }

        $this->logger->info('updated as out of stock: ' . $aff);

        $this->countOutOfStock = $aff;

        return $aff;
    }

    private function getDbItems(): iterable
    {
        $imageQuoted = $this->app->container->mysql->quote('image');

        return $this->app->managers->items->clear()
            ->setColumns([
                $this->app->managers->items->getEntity()->getPk(),
                'partner_item_id',
                'category_id',
                'partner_link_hash',
                new MysqlQueryExpression('SUBSTR(' . $imageQuoted . ', 1, ' . $this->app->images->getHashLength() . ') AS ' . $imageQuoted),
            ])
            ->setWhere([
                'import_source_id' => $this->source->getId(),
            ])
            ->getItems();
    }

    /**
     * @todo add table index...
     * @param array $partnerItemId
     * @return array
     */
    private function getPartnerUpdatedAtByPartnerItemId(array $partnerItemId): array
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

    /**
     * @todo add table index...
     * @param array $partnerItemId
     * @return array
     */
    private function getPartnerItemIdByPartnerItemId(array $partnerItemId): array
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

    /**
     * @todo add table index...
     * @param array $partnerItemId
     * @return array
     */
    private function getItemIdByPartnerItemId(array $partnerItemId): array
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

    private function getImageByItemId(array $itemId): array
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

    private function prepareSva()
    {
        $this->sva = [];

        foreach ($this->app->managers->catalog->getSvaComponents() as $entityClass) {
            /** @var string|Entity $entityClass */
            /** @var ItemAttrManager $manager */
            $manager = $this->app->managers->getByEntityClass($entityClass);

            $entity = $manager->getEntity();
            $table = $entity->getTable();

            [$nameToId, $uriToId] = $this->app->utils->attrs->getNameToIdAndUriToId($manager->copy(true), true);

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
                'processNew' => !!$this->app->config('import.' . $table, false),
            ];
        }
    }

    private function getAllSvaByRow($row)
    {
        $output = [];

        foreach ($this->sva as $entityPk => &$data) {
            /** @var Entity[]|Manager[] $data */

            if ($rawNameOrId = $this->{$data['method']}($row)) {
                $id = 0;

                if (is_int($rawNameOrId)) {
                    $id = $rawNameOrId;
                } else {
                    if (is_array($rawNameOrId)) {
                        $this->logger->warning('sva[' . $entityPk . '] as array found: ' . var_export($rawNameOrId, true));
                        $rawNameOrId = $rawNameOrId[0];
                    }

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
                        } catch (Throwable $e) {
                            $this->logger->error($e);
                        }
                    }
                }

                $output[$entityPk] = (int) $id;
            } else {
                $output[$entityPk] = null;
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
    private function getSvaByRow($pk, $row)
    {
        if (isset($this->mappings[$pk])) {
            $m = $this->mappings[$pk];

            if (isset($m['value']) && $m['value']) {
                return (int) $m['value'];
            } elseif (isset($m['column'])) {
                $c = $m['column'];

                if (isset($this->indexes[$c])) {
                    $from = trim($row[$this->indexes[$c]]);

                    if (array_key_exists('modify', $m) && array_key_exists($from, $modifies = $m['modify']) && $modifies[$from]['value']) {
                        return (int) $modifies[$from]['value'];
                    }

                    if ($this->sva[$pk]['processNew']) {
                        return $from;
                    }
                }
            }
        }

        if (isset($this->sources[$pk])) {
            foreach ((array) $this->sources[$pk] as $c) {
                if (isset($this->indexes[$c])) {
                    $i = $this->indexes[$c];

                    if ($source = trim($row[$i])) {
                        if ($this->isRuLang) {
                            foreach ($this->sva[$pk]['nameToId'] as $value => $id) {
                                if (false !== mb_stripos($source, $value)) {
                                    return (int) $id;
                                }
                            }
                        }

                        foreach ($this->sva[$pk]['termNameToId'] as $term => $id) {
                            if (false !== mb_stripos($source, $term)) {
                                return (int) $id;
                            }
                        }
                    }
                }
            }
        }

        return 0;
    }

    private function getBrandByRow($row)
    {
        return $this->getSvaByRow('brand_id', $row);
    }

    private function getVendorByRow($row)
    {
        return $this->source->getVendorId();
    }

    private function getImportSourceByRow($row)
    {
        return $this->source->getId();
    }

    /**
     * @param $partnerItemId
     * @param array $value - image_with_dimensions[]
     */
    private function rememberImages($partnerItemId, array $value)
    {
        if (isset($this->images[$partnerItemId])) {
            $this->images[$partnerItemId] = array_merge($this->images[$partnerItemId], $value);
        } else {
            $this->images[$partnerItemId] = $value;
        }
    }

    private function prepareMva()
    {
        $this->mva = [];

        foreach ($this->app->managers->catalog->getMvaComponents() as $entityClass) {
            /** @var string|Entity $entityClass */
            /** @var ItemAttrManager $manager */
            $manager = $this->app->managers->getByEntityClass($entityClass);

            $entity = $manager->getEntity();
            $table = $entity->getTable();

            [$nameToId, $uriToId] = $this->app->utils->attrs->getNameToIdAndUriToId($manager->copy(true), true);

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
                'processNew' => !!$this->app->config('import.' . $table, false),
            ];
        }
    }

    /**
     * Should be always synced with self::getCategoryByRow()
     * @param      $row
     * @param null $partnerItemId
     */
    private function rememberManualTagsByRow($row, $partnerItemId = null)
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

    /**
     * @param $row
     * @return array - image_with_dimensions[]
     */
    private function getDownloadedImagesByRow($row)
    {
        $images = $this->getImagesByRow($row);

        if ($this->downloadImages) {
            $output = [];

            foreach (explode(',', $row[$this->indexes[$this->mappings['image']['column']]]) as $k => $url) {
                if (!in_array($images[$k], $this->existingImages)) {
                    if ($url = trim($url)) {
                        $error = null;

                        if (!$hashWithDimensions = $this->app->images->downloadWithWget($url, $images[$k], $error)) {
                            // @todo check if ok
                            unset($images[$k]);

                            $this->logger->warning($url . ': ' . $error);
                            continue;
                        }

//                        $output[] = $images[$k];
                        $output[] = $hashWithDimensions;
                    }
                }
            }

            return $output;
        }

        return $images;
    }

    private function rememberDownloadedImagesByRow($row, $partnerItemId = null)
    {
        $partnerItemId = $partnerItemId ?: $this->getPartnerItemIdByRow($row);

        $this->rememberImages($partnerItemId, $this->getDownloadedImagesByRow($row));
    }

    /**
     * @@todo process dimensions...
     *
     */
    private function insertImages()
    {
        $manager = $this->app->managers->itemImages;
        $table = $manager->getEntity()->getTable();

        try {
            $insert = [];

            $partnerItemIdToItemId = $this->getItemIdByPartnerItemId(array_keys($this->images));
            $itemIdToImage = $this->getImageByItemId(array_values($partnerItemIdToItemId));

            foreach ($this->images as $partnerItemId => $images) {
                if (!isset($partnerItemIdToItemId[$partnerItemId])) {
                    // @todo checkout & fix
                    $this->logger->warning('$partnerItemIdToItemId[' . $partnerItemId . '] not found... skipping...');
                    continue;
                }

                $images = array_unique($images, SORT_REGULAR);

                $mainImage = $itemIdToImage[$partnerItemIdToItemId[$partnerItemId]];

                foreach ($images as $image) {
                    if ($mainImage != $image) {
                        $insert[] = [
                            'item_id' => (int) $partnerItemIdToItemId[$partnerItemId],
                            'image_id' => $image,
                        ];
                    }
                }
            }

            $aff = $insert ? $manager->insertMany($insert, ['ignore' => true, 'log' => $this->debug]) : 0;

            $this->logger->info('AFF ' . $table . ': ' . $aff);
        } catch (Throwable $e) {
            $this->logger->error($e);
        }

        $this->images = [];
    }

    private function insertMva()
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
                        $this->logger->warning('$partnerItemIdToItemId[' . $partnerItemId . '] not found... skipping...');
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
                                } catch (Throwable $e) {
                                    $this->logger->error($e);

                                    if (Exception::_check($e, 'Duplicate entry')) {
                                        $tmp = $this->app->container->mysql->selectOne($table, new MysqlQuery([
                                            'columns' => $entityPk,
                                            'where' => ['uri' => $newUri],
                                        ]));

                                        if ($tmp) {
                                            $id = $tmp[$entityPk];
                                            $this->mva[$entityPk]['uriToId'][$newUri] = $id;
                                            $this->mva[$entityPk]['nameToId'][$nameOrId] = $id;
                                        } else {
                                            $this->logger->warning('Can\'t figure out ' . $table . ' id in case of duplicate');
                                        }
                                    } else {
                                        $this->logger->warning('Can\'t figure out ' . $table . ' id');
                                    }
                                }
                            }
                        }

                        if ($id) {
                            $insert[] = [
                                $itemPk => (int) $partnerItemIdToItemId[$partnerItemId],
                                $entityPk => (int) $id,
                            ];
                        }
                    }
                }

                $aff = $insert ? $manager->getMvaLinkManager()->insertMany($insert, ['ignore' => true, 'log' => $this->debug]) : 0;

                $this->logger->info('AFF ' . $manager->getMvaLinkManager()->getEntity()->getTable() . ': ' . $aff);
            } catch (Throwable $e) {
                $this->logger->error($e);
            }

            $this->mva[$entityPk]['values'] = [];
        }
    }

    /**
     * Returns IDs
     * @param $pk
     * @param $row
     * @return array
     */
    private function getMvaByRow($pk, $row)
    {
        $output = [];

        if (isset($this->sources[$pk])) {
            foreach ((array) $this->sources[$pk] as $c) {
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

    private function getTagsByRow($row)
    {
        return $this->getMvaByRow('tag_id', $row);
    }

    private function getNameByRow($row): string
    {
        if (array_key_exists('_name', $row)) {
            return $row['_name'];
        }

        return $this->clearText($row[$this->indexes[$this->mappings['name']['column']]]);
    }

    private function getDescriptionByRow($row)
    {
        if (isset($this->mappings['description']) && isset($this->indexes[$this->mappings['description']['column']])) {
            $value = $this->clearText($row[$this->indexes[$this->mappings['description']['column']]]);

            if ($value) {
                return $value;
            }
        }


        return null;
    }

    /**
     * @param $row
     * @return array - image_without_dimensions[]
     */
    private function getImagesByRow($row): array
    {
        if (array_key_exists('_images', $row)) {
            return $row['_images'];
        }

        $output = [];

        foreach (explode(',', $row[$this->indexes[$this->mappings['image']['column']]]) as $k => $url) {
            if ($url = trim($url)) {
                # @todo pass categoryId salt in case of unique across categories (take into account: cats could changed, maps could chaned)
                $output[$k] = $this->app->images->getHash($url);
            }
        }

        return $output;
    }

    /**
     * @param $row
     * @return mixed - Image_with_dimensions
     */
    private function getDownloadedImageByRow($row)
    {
        $downloaded = $this->getDownloadedImagesByRow($row);

        $image = array_shift($downloaded);

        $this->rememberImages($this->getPartnerItemIdByRow($row), $downloaded);

        return $image;
    }

    private function getPriceByRow($row)
    {
        if (array_key_exists('_price', $row)) {
            return $row['_price'];
        }

        $v = (float) trim($row[$this->indexes[$this->mappings['price']['column']]]);

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
    private function getOldPriceByRow($row)
    {
        if (isset($this->mappings['old_price']) && $v = (float) trim($row[$this->indexes[$this->mappings['old_price']['column']]])) {
            if ($v > 999999.99) {
                return null;
            }

            return number_format($v, 2, '.', '');
        }

        return null;
    }

    private function getIsInStockByRow($row): int
    {
        if ($this->forceOutOfStock) {
            return 0;
        }

        if (isset($this->mappings['is_in_stock'])) {
            $map = $this->mappings['is_in_stock'];

            if (!array_key_exists('modify', $map)) {
                return 0;
            }

            $value = trim($row[$this->indexes[$map['column']]]);
            $modifies = $map['modify'];

            if (!array_key_exists($value, $modifies)) {
                return 0;
            }

            return (int) $modifies[$value]['value'];
        }

        return 1;
    }

    private function getPartnerLinkByRow($row): string
    {
        if (array_key_exists('_partner_link', $row)) {
            return $row['_partner_link'];
        }

        return trim($row[$this->indexes[$this->mappings['partner_link']['column']]]);
    }

    private function normalizePartnerLink($link)
    {
        return $link;
    }

    private function getPartnerLinkHashByRow($row): string
    {
        if (array_key_exists('_partner_link_hash', $row)) {
            return $row['_partner_link_hash'];
        }

        return md5($this->normalizePartnerLink($this->getPartnerLinkByRow($row)));
    }

    private function getEntityByRow($row): ?string
    {
        if (isset($this->mappings['entity']) && isset($this->indexes[$this->mappings['entity']['column']])) {
            $map = $this->mappings['entity'];
            $value = $this->clearText($row[$this->indexes[$map['column']]]);

            if (array_key_exists('modify', $map) && array_key_exists($value, $modifies = $map['modify']) && strlen($modifies[$value]['value'])) {
                $value = $modifies[$value]['value'];
            }

            return $value;
        }

        return null;
    }

    private function getPartnerUpdatedAtByRow($row)
    {
        if (array_key_exists('_partner_updated_at', $row)) {
            return $row['_partner_updated_at'];
        }

        return isset($row[$this->indexes[$this->mappings['partner_updated_at']['column']]])
            ? (int) trim($row[$this->indexes[$this->mappings['partner_updated_at']['column']]])
            : $this->microtime;
    }

    private function insertItems(): ?int
    {
        try {
            $this->logger->info('-----------PROGRESS-----------');
            $this->logger->info('PASSED = ' . $this->passed);
            $this->logger->info('SKIPPED by unique key = ' . $this->skippedByUnique);
            $this->logger->info('SKIPPED by updated at = ' . $this->skippedByUpdated);
            $this->logger->info('SKIPPED by other = ' . $this->skippedByOther);
            $this->logger->info('SKIPPED = ' . ($this->skippedByOther + $this->skippedByUnique + $this->skippedByUpdated));
            $this->logger->info('------------------------------');

            if ((!$this->keys) || (!$this->bindValues) || (!$this->index)) {
                $this->logger->warning('empty runtime values', [
                    'keys' => $this->keys,
                    'bindValues' => $this->bindValues,
                    'index' => $this->index,
                ]);

                return null;
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
                'updated_at',
            ];

            $editableColumns = $this->isForceUpdate ? [] : self::getPostEditableColumns();

            $mysql = $this->app->container->mysql;

            $onDuplicateClosure = function ($column, $value) use ($mysql) {
                return $mysql->quote($column) . ' = ' . $value;
            };

            $query = new MysqlQuery();
            $query->text = implode(' ', [
                'INSERT INTO',
                $mysql->quote($this->app->managers->items->getEntity()->getTable()),
                '(' . implode(', ', array_map(function ($i) use ($mysql) {
                    return $mysql->quote($i);
                }, $this->keys)) . ') VALUES ' . implode(', ', array_map(function ($i) {
                    return '(' . $i . ')';
                }, $tmp)),
                'ON DUPLICATE KEY UPDATE',

                //@todo next code overwrites all items fixes... (+add fixWhere clauses maybe?! +take into account "updated_at" in this query)

                implode(', ', array_map(function ($column) use ($onDuplicateClosure, $mysql) {
                    return $onDuplicateClosure($column, 'VALUES(' . $mysql->quote($column) . ')');
                }, array_diff($this->keys, $onDuplicateIgnoreColumns, $editableColumns))) . ',',

                implode(', ', array_map(function ($column) use ($onDuplicateClosure, $mysql, $columnsOptions) {
                    $options = $columnsOptions[$column];

                    return $onDuplicateClosure($column, 'IF(' . implode(', ', [
                            implode(' OR ', array_filter([
                                !in_array(Entity::REQUIRED, $options) ? ($mysql->quote($column) . ' IS NULL') : null,
                                Entity::COLUMN_INT == $options['type'] ? ($mysql->quote($column) . ' = 0') : null,
                                Entity::COLUMN_TEXT == $options['type'] ? ($mysql->quote($column) . ' = \'\'') : null,
                            ], function ($v) {
                                return null !== $v;
                            })),
                            'VALUES(' . $mysql->quote($column) . ')',
                            $mysql->quote($column),
                        ]) . ')');
                }, $editableColumns)) . ($editableColumns ? ',' : ''),
                $onDuplicateClosure('updated_at', 'NOW()'),
            ]);
            $query->params = $this->bindValues;
            $query->log = $this->debug;

            $aff = $mysql->req($query)->affectedRows();

            $this->logger->info('AFF item: ' . $aff);
            return $aff;
        } catch (Throwable $e) {
            $this->logger->error($e);
            return null;
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    private function getHash(): string
    {
        return md5(implode('', [
            md5_file($this->getDownloadedCsvFileName()),
            $this->getFilename(),
            $this->source->getFileFilter(),
            $this->source->getFileMapping(),
        ]));
    }

    private function createHistory(string $hash)
    {
        $this->history = (new ImportHistory)
            ->setImportSourceId($this->source->getId())
            ->setError('unknown error')
            ->setHash($hash);

        $this->app->managers->importHistory->insertOne($this->history);

        $this->logger->info('history id: ' . $this->history->getId());

        return $this;
    }

    private function updateHistory()
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
                ->setCountOutOfStock($this->countOutOfStock)
                ->setError($this->error));
        } else {
            $this->logger->warning('invalid history object');
        }

        return $this;
    }

    private function addToLinkGroups($category, $key, $partnerItemId)
    {
        if (!isset($this->linkGroups[$category])) {
            $this->linkGroups[$category] = [];
        }

        if (isset($this->linkGroups[$category][$key])) {
            if (is_array($this->linkGroups[$category][$key])) {
                if (!in_array($partnerItemId, $this->linkGroups[$category][$key])) {
                    $this->linkGroups[$category][$key][] = $partnerItemId;
                }
            } else {
                if ($this->linkGroups[$category][$key] != $partnerItemId) {
                    $this->linkGroups[$category][$key] = [$this->linkGroups[$category][$key], $partnerItemId];
                }
            }
        } else {
            $this->linkGroups[$category][$key] = $partnerItemId;
        }
    }

    private function addToImageGroups($category, $key, $partnerItemId)
    {
        if (!isset($this->imageGroups[$category])) {
            $this->imageGroups[$category] = [];
        }

        if (isset($this->imageGroups[$category][$key])) {
            if (is_array($this->imageGroups[$category][$key])) {
                if (!in_array($partnerItemId, $this->imageGroups[$category][$key])) {
                    $this->imageGroups[$category][$key][] = $partnerItemId;
                }
            } else {
                if ($this->imageGroups[$category][$key] != $partnerItemId) {
                    $this->imageGroups[$category][$key] = [$this->imageGroups[$category][$key], $partnerItemId];
                }
            }
        } else {
            $this->imageGroups[$category][$key] = $partnerItemId;
        }
    }

    /**
     * @param $row
     * @return array
     */
    private function getRowDuplicates($row)
    {
        $category = $this->getCategoryByRow($row);
        $link = $this->getPartnerLinkHashByRow($row);

//        if (isset($this->linkGroups[$category][$link])) {
//            return $this->linkGroups[$category][$link];
//        }

        if (isset($this->linkGroups[$category][$link]) && is_array($this->linkGroups[$category][$link])) {
            return $this->linkGroups[$category][$link];
        }

        return [];
    }

    private function getRowGarbage($row)
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

    private function dropRowGarbage($row)
    {
        $category = $this->getCategoryByRow($row);
        $image = $this->getImagesByRow($row)[0];

        unset($this->imageGroups[$category][$image]);
    }

    /**
     * Collects existing (in db; already imported) rows patner item ids and theirs duplicates (if has) patner item ids
     * @param array $rows
     * @return array
     */
    private function collectExistingPartnerItemId(array $rows): array
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
     * @param array $rows
     * @return array - image_without_image[]
     */
    private function collectExistingImages(array $rows): array
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

        $mysql = $this->app->container->mysql;

        foreach ($this->app->managers->items->clear()
                     ->setColumns([
                         'partner_item_id',
                         new MysqlQueryExpression('SUBSTR(' . $mysql->quote('image') . ', 1, ' . $this->app->images->getHashLength() . ') AS ' . $mysql->quote('image')),
                     ])
                     ->setWhere([
                         'import_source_id' => $this->source->getId(),
                     ])
                     ->setHavings([
                         'image' => $images,
                     ])
                     ->setQueryParam('placeholders', false)
                     ->setQueryParam('log', $this->debug)
                     ->getItems() as $v) {
            $output[] = $v['image'];
        }

        // non existing images
//        $images = array_diff($images, $output);

        // existing images
        $images = $output;
        $output = [];

        $query = new MysqlQuery(['params' => []]);
        $query->text = implode(' ', [
            $mysql->makeSelectSQL([
                'partner_item_id',
                new MysqlQueryExpression('SUBSTR(' . $mysql->quote('image_id') . ', 1, ' . $this->app->images->getHashLength() . ') AS ' . $mysql->quote('image_id')),
            ], false, $query->params),
            $mysql->makeFromSQL($this->app->managers->items->getEntity()->getTable()),
            $mysql->makeJoinSQL([[
                $this->app->managers->items->getEntity()->getTable(),
                $this->app->managers->itemImages->getEntity()->getTable(),
                $this->app->managers->items->getEntity()->getPk(),
            ]], $query->params),
            $mysql->makeWhereSQL([
                'import_source_id' => $this->source->getId(),
            ], $query->params, null, $query->placeholders),
            $mysql->makeHavingSQL([
                'image_id' => $images,
            ], $query->params),
        ]);
        $query->placeholders = false;
        $query->log = $this->debug;

        foreach ($mysql->reqToArrays($query) as $row) {
            $output[] = $row['image_id'];
        }

        return $output;
    }

    private function rememberRow($row)
    {
        try {
            $values = [];

            $this->beforeRememberRow($row);

            if (!$values['partner_item_id'] = $this->getPartnerItemIdByRow($row)) {
                return false;
            }

            $values = array_merge($values, $this->getAllSvaByRow($row));

            if (!$values['category_id'] || !$values['brand_id']) {
                $this->logger->info('[SKIPPED category or brand] partner_id=' . $values['partner_item_id']);
                return false;
            }

            if (!$values['name'] = $this->getNameByRow($row)) {
                $this->logger->info('[SKIPPED name] partner_id=' . $values['partner_item_id']);
                return false;
            }

            if (!$values['price'] = $this->getPriceByRow($row)) {
                $this->logger->info('[SKIPPED price] partner_id=' . $values['partner_item_id']);
                return false;
            }

            if (!$values['partner_link'] = $this->getPartnerLinkByRow($row)) {
                $this->logger->info('[SKIPPED link] partner_id=' . $values['partner_item_id']);
                return false;
            }

            # @todo optimize
            $values['partner_link_hash'] = $this->getPartnerLinkHashByRow($row);

            if (!$values['image'] = $this->getDownloadedImageByRow($row)) {
                $this->logger->info('[SKIPPED image] partner_id=' . $values['partner_item_id']);
                return false;
            }

            $values['import_source_id'] = $this->getImportSourceByRow($row);
            $values['old_price'] = $this->getOldPriceByRow($row);

            if ($values['is_in_stock'] = $this->getIsInStockByRow($row)) {
                $this->inStockFilePartnerItemId[] = $values['partner_item_id'];
            }

            $values['entity'] = $this->getEntityByRow($row);
            $values['description'] = $this->getDescriptionByRow($row);
            $values['is_sport'] = empty($this->sport[$values['partner_item_id']]) ? 0 : 1;
            $values['is_size_plus'] = empty($this->sizePlus[$values['partner_item_id']]) ? 0 : 1;
            $values['partner_updated_at'] = $this->getPartnerUpdatedAtByRow($row);

            foreach ($this->requiredColumns as $dbColumn) {
                if (isset($values[$dbColumn]) && !mb_strlen($values[$dbColumn])) {
                    $this->logger->info('[SKIPPED required ' . $dbColumn . ']=' . var_export($values[$dbColumn], true) . ' partner_id=' . $values['partner_item_id']);
                    return false;
                }
            }

            $this->rememberAllMvaByRow($row);
            $this->afterRememberRow($row);

            return $values;
        } catch (Throwable $e) {
            $this->logger->error($e);
            return false;
        }
    }

    /**
     * @return Import
     * @throws \Exception
     */
    private function check(): Import
    {
        if ('cli' == PHP_SAPI && !$this->source->isCron()) {
            throw new Exception('[import_source_id=' . $this->source->getName() . '] is out of cron');
        }

        return $this;
    }

    private function getPidFilename(): string
    {
        return implode('/', [
            $this->app->dirs['@tmp'],
            implode('_', [
                'import_pid',
                $this->source->getId(),
            ]),
        ]);
    }

    private function checkPid(): bool
    {
        return FileSystem::isFileExists($this->getPidFilename());
    }

    private function createPid(): bool
    {
        $file = $this->getPidFilename();

        $output = FileSystem::createFile($file, $this->source->getId());

        if ($output) {
            $this->setAccessPermissions($file);
        }

        return $output;
    }

    private function deletePid(): bool
    {
        return FileSystem::deleteFile($this->getPidFilename());
    }

    private function clearText(string $text): string
    {
        $text = htmlspecialchars_decode($text);
        $text = html_entity_decode($text);
        $text = strip_tags($text);
        $text = trim($text);

        return $text;
    }

    private function profile(string $metric, callable $job)
    {
        if ($this->profile) {
            $context = [];

            $time = microtime(true);
            $memory = memory_get_usage(true);

            try {
                return $job($metric);
            } catch (Throwable $e) {
                $context['error'] = true;
            } finally {
                $time = (float) substr(microtime(true) - $time, 0, 7);
                $memory = memory_get_usage(true) - $memory;

                $this->logger->info($metric . '.time: ' . $time, $context);
                $this->logger->info($metric . '.memory: ' . $memory, $context);

                if (!array_key_exists($metric, $this->profileData)) {
                    $this->profileData[$metric] = [
                        'time' => [],
                        'memory' => [],
                    ];
                }

                $this->profileData[$metric]['time'][] = $time;
                $this->profileData[$metric]['memory'][] = $memory;
            }
        }

        return $job($metric);
    }
}
