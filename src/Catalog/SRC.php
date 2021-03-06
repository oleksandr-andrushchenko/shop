<?php

namespace SNOWGIRL_SHOP\Catalog;

use SNOWGIRL_CORE\AbstractApp as App;
use SNOWGIRL_CORE\Cache\MemcacheException;
use SNOWGIRL_SHOP\Catalog\SRC\DataProvider;

use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Entity\Item;

use SNOWGIRL_CORE\Entity\Page;

use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalog;
use SNOWGIRL_SHOP\Entity\Page\Catalog\Custom as PageCatalogCustom;
use stdClass;
use Throwable;

class SRC
{
    private $uri;
    /**
     * @var Entity[]
     */
    private $entities;

    private $dataProvider;

    private $items;
    private $totalCount;
    private $offset;
    private $limit;
    private static $showValues;
    private static $orderValues;
    private $page;
    private $catalogPage;
    private $catalogCustomPage;
    private $useCache;
    private $providerName;

    /**
     * @param URI $uri
     * @param array $entities - attrs entities to collect (used in templates, e.g. - entity.item.catalog.phtml )
     * @todo    !!! create separate Strategies (classes implemented from common interface) instead of raw mods
     */
    public function __construct(URI $uri, array $entities = [])
    {
        $this->uri = $uri;
        $this->entities = Manager::mapEntitiesAddPksAsKeys($entities);
        $this->useCache = !!$uri->getApp()->config('catalog.cache', false);
        $this->providerName = $this->getURI()->getApp()->config('data_provider.src', 'mysql');
    }

    public function getDataProvider(string $forceProvider = null): DataProvider
    {
        if ((null === $forceProvider) || ($forceProvider == $this->providerName)) {
            if (null == $this->dataProvider) {

                $class = __CLASS__ . '\\DataProvider\\' . ucfirst($this->providerName) . 'DataProvider';

                $this->dataProvider = new $class($this);
            }

            return $this->dataProvider;
        }

        $class = __CLASS__ . '\\DataProvider\\' . ucfirst($forceProvider) . 'DataProvider';

        return new $class($this);
    }

    public function getURI(): URI
    {
        return $this->uri;
    }

    public function getMasterServices()
    {
        return $this->uri->getApp()->managers->items->getMasterServices();
    }

    public function getEntities(): array
    {
        return $this->entities;
    }

    private function getItemsRawCacheKey(): string
    {
        return md5(serialize([$this->getURI()->getParams(), array_keys($this->entities)]));
    }

    private function getItemsIdToAttrsCacheKey(): string
    {
        return implode('-', [
            $this->getURI()->getApp()->managers->items->getEntity()->getTable(),
            $this->getItemsRawCacheKey(),
            $this->providerName,
            'ids',
        ]);
    }

    private function getItemsCountCacheKey(): string
    {
        return implode('-', [
            $this->getURI()->getApp()->managers->items->getEntity()->getTable(),
            $this->getItemsRawCacheKey(),
            $this->providerName,
            'total',
        ]);
    }

    public function getItemsIdToAttrs(): array
    {
        $fn = function () {
            $output = [];

            foreach ($this->getDataProvider()->getItemsAttrs() as $item) {
                $id = (int) $item['item_id'];
                unset($item['item_id']);
                $output[$id] = $item;
            }

            return $output;
        };

        if ($this->useCache) {
            $cacheKey = $this->getItemsIdToAttrsCacheKey();
            $cache = $this->getURI()->getApp()->container->memcache($this->getMasterServices());

            if (!$cache->has($cacheKey, $output)) {
                $cache->set($cacheKey, $output = $fn());
            }

            return $output;
        }

        return $fn();
    }

    public function getItemsId(): array
    {
        $tmp = $this->entities;
        $this->entities = [];
        $output = array_keys($this->getItemsIdToAttrs());
        $this->entities = $tmp;

        return $output;
    }

    /**
     * @param bool $total
     * @return Item[]
     * @throws MemcacheException
     */
    public function getItems(&$total = false): array
    {
        if (null === $total) {
            if ($this->useCache) {
                $this->getURI()->getApp()->container->memcache($this->getMasterServices())->getMulti([
                    $this->getItemsIdToAttrsCacheKey(),
                    $this->getItemsCountCacheKey()
                ]);
            }

            $total = $this->getTotalCount();
        }

        if (is_array($this->items)) {
            if (false !== $total) {
                $total = $this->getTotalCount();
            }

            return $this->items;
        }

        $itemIdToAttrs = $this->getItemsIdToAttrs();

        /** @var Item[] $items */
        $items = $this->getURI()->getApp()->managers->items->populateList(array_keys($itemIdToAttrs));
        $items = Arrays::mapByKeyMaker($items, function ($item) {
            /** @var Item $item */
            return $item->getId();
        });

        $entityToAttrId = [];

        foreach ($this->entities as $attrKey => $attrEntity) {
            /** @var string $attrEntity */
            $attrId = [];

            foreach ($itemIdToAttrs as $attrs) {
                if ($attrs[$attrKey]) {
                    foreach (explode(',', $attrs[$attrKey]) as $attrId2) {
                        $attrId[] = (int) $attrId2;
                    }
                }
            }

            $entityToAttrId[$attrEntity] = $attrId;
        }

        $itemColumns = $this->getURI()->getApp()->managers->items->getEntity()->getColumns();

        foreach ($entityToAttrId as $attrEntity => $attrId) {
            /** @var Entity $attrEntity */
            $attrKey = $attrEntity::getPk();
            $attrId = array_unique($attrId);
            $manager = $this->getURI()->getApp()->managers->getByEntityClass($attrEntity);

            $attrIdToAttrObject = Arrays::mapByKeyMaker($manager->populateList($attrId), function ($entity) {
                /** @var Entity $entity */
                return $entity->getId();
            });

            foreach ($itemIdToAttrs as $itemId => $attrs) {
                if ($attrs[$attrKey] && isset($items[$itemId])) {
                    $attrObject = [];

                    foreach (explode(',', $attrs[$attrKey]) as $attrId2) {
                        if (isset($attrIdToAttrObject[$attrId2])) {
                            $attrObject[] = $attrIdToAttrObject[$attrId2];
                        }
                    }

                    if ($attrObject) {
                        $items[$itemId]->setLinked($attrKey, isset($itemColumns[$attrKey]) ? $attrObject[0] : $attrObject);
                    }
                }
            }
        }

        $this->items = array_values($items);

        return $this->items;
    }

    public function getFirstItem(bool $mostRated = false): ?Item
    {
        if ($items = $this->getItems()) {
            $output = $items[0];

            if ($mostRated) {
                foreach ($items as $item) {
                    if ($item->getRating() > $output->getRating()) {
                        $output = $item;
                    }
                }
            }

            return $output;
        }

        return null;
    }

    public function getTotalCount(): int
    {
        if (null === $this->totalCount) {
            $fn = function () {
                return $this->getDataProvider()->getTotalCount();
            };

            if ($this->useCache) {
                $this->totalCount = $fn();
            } else {
                $cacheKey = $this->getItemsCountCacheKey();
                $cache = $this->getURI()->getApp()->container->memcache($this->getMasterServices());

                if (!$cache->has($cacheKey, $output)) {
                    $cache->set($cacheKey, $output = $this->getDataProvider()->getTotalCount());
                }

                $this->totalCount = $output;
            }
        }

        return $this->totalCount;
    }

    public function getOrderInfo(): stdClass
    {
        $v = $this->getURI()->get(URI::ORDER);

        if (!in_array($v, self::getOrderValues())) {
            $v = self::getDefaultOrderValue();
        }

        if ($desc = 0 === strpos($v, '-')) {
            $v = substr($v, 1);
        }

        return (object) [
            'column' => 'relevance' == $v ? null : $v,
            'cache_column' => $vv = implode('_', ['order', $desc ? 'desc' : 'asc', $v]),
            'cache_index' => 'ix_' . $vv,
            'order' => $desc,
        ];
    }

    public function setOffset($offset): SRC
    {
        $this->offset = $offset;

        return $this;
    }

    public function getOffset()
    {
        if (null === $this->offset) {
            $this->offset = ($this->getPageNum() - 1) * $this->getLimit();
        }

        return $this->offset;
    }

    public function setLimit($rowCount): SRC
    {
        $this->limit = $rowCount;

        return $this;
    }

    public function getLimit()
    {
        if (null === $this->limit) {
            $v = $this->getURI()->get(URI::PER_PAGE);

            if (!in_array($v, self::getShowValues($this->getURI()->getApp())) && !$this->getURI()->get(URI::EVEN_NOT_STANDARD_PER_PAGE)) {
                $v = self::getDefaultShowValue($this->getURI()->getApp());
            }

            $this->limit = (int) $v;
        }

        return $this->limit;
    }

    public function getPageNum()
    {
        return $this->getURI()->get(URI::PAGE_NUM, 1);
    }

    public static function getShowValues(App $app): array
    {
        return self::$showValues ?: self::$showValues = explode(',', $app->config('catalog.show'));
    }

    public static function getDefaultShowValue(App $app)
    {
        $tmp = self::getShowValues($app);

        return current($tmp);
    }

    public static function getOrderValues(): array
    {
        return self::$orderValues ?: self::$orderValues = [
            '-relevance',
            '-rating',
            'price',
            '-price',
//            'newly',
//            'sales',
        ];
    }

    public static function getDefaultOrderValue()
    {
        $tmp = self::getOrderValues();

        return current($tmp);
    }

    public function getItemsPricesRange(): array
    {
        $tmp = [];

        foreach ($this->getItems() as $item) {
            $tmp[] = $item->getPrice();
        }

        return [
            'min' => $tmp ? min($tmp) : 0,
            'max' => $tmp ? max($tmp) : 0
        ];
    }

    public static function getTypesToColumns(): array
    {
        return array_merge(array_combine(URI::TYPE_PARAMS, array_fill(0, count(URI::TYPE_PARAMS), null)), [
            URI::SPORT => 'is_sport',
            URI::SIZE_PLUS => 'is_size_plus',
            URI::SALES => 'is_sales'
        ]);
    }

    public function getLastPage()
    {
        $total = $this->getTotalCount();
        $size = $this->getLimit();

        $output = intval($total / $size);

        if ($total % $size) {
            $output++;
        }

        if ($output < 1) {
            $output = 1;
        }

        return $output;
    }

    public function isLastPage(): bool
    {
        return $this->getPageNum() == $this->getLastPage();
    }

    public function getPage(): Page
    {
        if (null === $this->page) {
            $this->page = $this->getURI()->getApp()->managers->pages->findByKey('catalog');
        }

        return $this->page;
    }

    public function setCatalogPage(PageCatalog $page): SRC
    {
        $this->catalogPage = $page;

        return $this;
    }

    /**
     * @param bool $retrieve
     * @return bool|mixed|null|PageCatalog
     * @throws Throwable
     */
    public function getCatalogPage(bool $retrieve = false)
    {
        if (null !== $this->catalogPage) {
            return $this->catalogPage;
        }

        if (!$this->getURI()->isCatalogPage()) {
            return $this->catalogPage = false;
        }

        if (!$retrieve) {
            return null;
        }

        //@todo check...
        if (!$params = $this->getURI()->getParams()) {
            return $this->catalogPage = false;
        }

        if (!$page = $this->getURI()->getApp()->managers->catalog->clear()->getObjectByParams($params)) {
            return $this->catalogPage = false;
        }

        return $this->catalogPage = $page;
    }

    /**
     * @param bool $retrieve
     * @return bool|PageCatalogCustom
     * @throws Throwable
     */
    public function getCatalogCustomPage(bool $retrieve = false)
    {
        if (null !== $this->catalogCustomPage) {
            return $this->catalogCustomPage;
        }

        if (!$catalog = $this->getCatalogPage($retrieve)) {
            return $this->catalogCustomPage = false;
        }

        if (!$page = $this->getURI()->getApp()->managers->catalog->getPageCatalogCustom($catalog)) {
            return $this->catalogCustomPage = false;
        }

        return $this->catalogCustomPage = $page;
    }

    /**
     * @return array
     * @throws Throwable
     */
    public function getAliases(): array
    {
        if ($page = $this->getCatalogPage()) {
            return $page->getMetaKey('aliases', []);
        }

        return [];
    }

    /**
     * @param $k
     * @return bool|Item\Attr\Alias
     * @throws Throwable
     */
    public function getAliasObject($k)
    {
        $aliases = $this->getAliases();

        if (isset($aliases[$k])) {
            return $this->getURI()->getApp()->managers->getByEntityPk($k)->getAliasManager()->find($aliases[$k]);
        }

        return false;
    }
}