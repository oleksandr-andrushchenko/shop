<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 12/02/15
 * Time: 9:08 AM
 */

namespace SNOWGIRL_SHOP\Catalog;

use SNOWGIRL_SHOP\Catalog\SRC\DataProvider;

use SNOWGIRL_CORE\App;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Entity\Item;

use SNOWGIRL_CORE\Entity\Page\Regular as PageRegular;

use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalog;
use SNOWGIRL_SHOP\Entity\Page\Catalog\Custom as PageCatalogCustom;

/**
 * @todo    !!! create separate Strategies (classes implemented from common interface) instead of raw mods
 *
 * Class SRC
 * @package SNOWGIRL_SHOP\Catalog
 */
class SRC
{
    protected $uri;
    /** @var array */
    protected $entities;
    protected $masterServices;
    protected $maxMatched;

    /**
     * @param URI        $uri
     * @param array      $entities   - attrs entities to collect (used in templates, e.g. - entity.item.catalog.phtml )
     * @param bool|false $maxMatched - is order by max matched (attributes counts)
     */
    public function __construct(URI $uri, array $entities = [], $maxMatched = false)
    {
        $this->uri = $uri;
        $this->entities = Manager::mapEntitiesAddPksAsKeys($entities);
        $this->masterServices = $this->uri->getApp()->managers->items->getMasterServices();
        $this->maxMatched = !!$maxMatched;
    }

    protected $dataProvider;

    public function getDataProvider(string $forceProvider = null): DataProvider
    {
        $provider = $this->getURI()->getApp()->config->{'data.provider'}->src('mysql');

        if ((null === $forceProvider) || ($forceProvider == $provider)) {
            if (null == $this->dataProvider) {
                $class = __CLASS__ . '\\DataProvider\\' . ucfirst($provider);

                $this->dataProvider = new $class($this);
            }

            return $this->dataProvider;
        }

        $class = __CLASS__ . '\\DataProvider\\' . ucfirst($forceProvider);

        return new $class($this);
    }

    public function getURI()
    {
        return $this->uri;
    }

    public function getMasterServices()
    {
        return $this->masterServices;
    }

    public function getEntities()
    {
        return $this->entities;
    }

    public function getMaxMatched()
    {
        return $this->maxMatched;
    }

    protected function getItemsRawCacheKey()
    {
        return md5(serialize([$this->uri->getParams(), array_keys($this->entities)]));
    }

    protected function getItemsIdToAttrsCacheKey()
    {
        return implode('-', [
            Item::getTable(),
            $this->getItemsRawCacheKey(),
            'ids'
        ]);
    }

    protected function getItemsCountCacheKey()
    {
        return implode('-', [
            Item::getTable(),
            $this->getItemsRawCacheKey(),
            'total'
        ]);
    }

    public function getItemsIdToAttrs()
    {
        $key = $this->getItemsIdToAttrsCacheKey();

        if (false !== ($output = $this->uri->getApp()->services->mcms(null, null, $this->masterServices)->get($key))) {
            return $output;
        }

        $output = [];

//        var_dump($this->getDataProvider()->getItemsAttrs());die;

        foreach ($this->getDataProvider()->getItemsAttrs() as $item) {
            $id = (int)$item['item_id'];
            unset($item['item_id']);
            $output[$id] = $item;
        }

        $this->uri->getApp()->services->mcms(null, null, $this->masterServices)->set($key, $output);
        return $output;
    }

    public function getItemsId()
    {
        $tmp = $this->entities;
        $this->entities = [];
        $output = array_keys($this->getItemsIdToAttrs());
        $this->entities = $tmp;
        return $output;
    }

    protected $items;

    /**
     * @param bool|false $total
     *
     * @return Item[]
     */
    public function getItems(&$total = false)
    {
        if (null === $total) {
            $this->uri->getApp()->services->mcms(null, null, $this->masterServices)->prefetch([
                $this->getItemsIdToAttrsCacheKey(),
                $this->getItemsCountCacheKey()
            ]);

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
        $items = $this->uri->getApp()->managers->items->populateList(array_keys($itemIdToAttrs));
        $items = Arrays::mapByKeyMaker($items, function ($item) {
            /** @var Item $item */
            return $item->getId();
        });

        $entityToAttrId = [];

        foreach ($this->entities as $attrKey => $attrEntity) {
            /** @var string $attrEntity */
            $attrId = [];

//            print_r([
//                $this->entities,
//                $itemIdToAttrs
//            ]);die;

            foreach ($itemIdToAttrs as $attrs) {
                if ($attrs[$attrKey]) {
                    foreach (explode(',', $attrs[$attrKey]) as $attrId2) {
                        $attrId[] = (int)$attrId2;
                    }
                }
            }

            $entityToAttrId[$attrEntity] = $attrId;
        }

        $itemColumns = Item::getColumns();

        foreach ($entityToAttrId as $attrEntity => $attrId) {
            /** @var Entity $attrEntity */
            $attrKey = $attrEntity::getPk();
            $attrId = array_unique($attrId);
            $manager = $this->uri->getApp()->managers->getByEntityClass($attrEntity);

            $attrIdToAttrObject = Arrays::mapByKeyMaker($manager->populateList($attrId), function ($entity) {
                /** @var Entity $entity */
                return $entity->getId();
            });

            foreach ($itemIdToAttrs as $itemId => $attrs) {
                if ($attrs[$attrKey] && isset($items[$itemId])) {
                    $attrObject = [];

                    foreach (explode(',', $attrs[$attrKey]) as $attrId2) {
                        $attrObject[] = $attrIdToAttrObject[$attrId2];
                    }

                    $items[$itemId]->setLinked($attrKey, isset($itemColumns[$attrKey]) ? $attrObject[0] : $attrObject);
                }
            }
        }

        $this->items = array_values($items);

        return $this->items;
    }

    /**
     * @param bool|false $mostRated
     *
     * @return null|Item
     */
    public function getFirstItem($mostRated = false)
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

    protected $totalCount;

    public function getTotalCount()
    {
        if (null === $this->totalCount) {
            $this->totalCount = $this->uri->getApp()->services->mcms(null, null, $this->masterServices)
                ->call($this->getItemsCountCacheKey(), function () {
                    return $this->getDataProvider()->getTotalCount();
                });
        }

        return $this->totalCount;
    }

    public function getOrderInfo()
    {
        $v = $this->uri->get(URI::ORDER);

        if (!in_array($v, self::getOrderValues())) {
            $v = self::getDefaultOrderValue();
        }

        if ($desc = 0 === strpos($v, '-')) {
            $v = substr($v, 1);
        }

        return (object)[
            'column' => $v,
            'cache_column' => $vv = implode('_', ['order', $desc ? 'desc' : 'asc', $v]),
            'cache_index' => 'ix_' . $vv,
            'desc' => $desc
        ];
    }

    protected $offset;

    public function setOffset($offset)
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

    protected $limit;

    public function setLimit($rowCount)
    {
        $this->limit = $rowCount;
        return $this;
    }

    public function getLimit()
    {
        if (null === $this->limit) {
            $v = $this->uri->get(URI::PER_PAGE);

            if (!in_array($v, self::getShowValues($this->uri->getApp())) && !$this->uri->get(URI::EVEN_NOT_STANDARD_PER_PAGE)) {
                $v = self::getDefaultShowValue($this->uri->getApp());
            }

            $this->limit = (int)$v;
        }

        return $this->limit;
    }

    public function getPageNum()
    {
        return $this->uri->get(URI::PAGE_NUM, 1);
    }

    protected static $showValues;

    /**
     * @param \SNOWGIRL_CORE\App|App $app
     *
     * @return array
     */
    public static function getShowValues(App $app)
    {
        return self::$showValues ?: self::$showValues = explode(',', $app->config->catalog->show);
    }

    /**
     * @param \SNOWGIRL_CORE\App|App $app
     *
     * @return mixed
     */
    public static function getDefaultShowValue(App $app)
    {
        $tmp = self::getShowValues($app);
        return current($tmp);
    }

    protected static $orderValues;

    /**
     * @todo if change - sync with tables order columns...
     * @return array
     */
    public static function getOrderValues()
    {
        return self::$orderValues ?: self::$orderValues = [
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

    /**
     * @return array
     */
    public function getItemsPricesRange()
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

    public static function getTypesToColumns()
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

    public function isLastPage()
    {
        return $this->getPageNum() == $this->getLastPage();
    }

    protected $regularPage;

    /**
     * @return PageRegular
     */
    public function getRegularPage()
    {
        if (null === $this->regularPage) {
            $this->regularPage = $this->uri->getApp()->managers->pages->findByKey('catalog');
        }

        return $this->regularPage;
    }

    protected $catalogPage;

    /**
     * @param PageCatalog $page
     *
     * @return $this
     */
    public function setCatalogPage(PageCatalog $page)
    {
        $this->catalogPage = $page;
        return $this;
    }

    /**
     * @param bool $retrieve
     *
     * @return bool|mixed|null|PageCatalog
     * @throws \Exception
     */
    public function getCatalogPage($retrieve = false)
    {
        if (null !== $this->catalogPage) {
            return $this->catalogPage;
        }

        if (!$this->uri->isCatalogPage()) {
            return $this->catalogPage = false;
        }

        if (!$retrieve) {
            return null;
        }

        //@todo check...
        if (!$params = $this->uri->getParams()) {
            return $this->catalogPage = false;
        }

        if (!$page = $this->uri->getApp()->managers->catalog->clear()->getObjectByParams($params)) {
            return $this->catalogPage = false;
        }

        return $this->catalogPage = $page;
    }

    protected $catalogCustomPage;

    /**
     * @param bool $retrieve
     *
     * @return bool|PageCatalogCustom
     * @throws \Exception
     */
    public function getCatalogCustomPage($retrieve = false)
    {
        if (null !== $this->catalogCustomPage) {
            return $this->catalogCustomPage;
        }

        if (!$catalog = $this->getCatalogPage($retrieve)) {
            return $this->catalogCustomPage = false;
        }

        if (!$page = $this->uri->getApp()->managers->catalog->getPageCatalogCustom($catalog)) {
            return $this->catalogCustomPage = false;
        }

        return $this->catalogCustomPage = $page;
    }

    /**
     * @return array|null
     * @throws \Exception
     */
    public function getAliases()
    {
        if ($page = $this->getCatalogPage()) {
            return $page->getMetaKey('aliases', []);
        }

        return [];
    }

    /**
     * @param $k
     *
     * @return bool
     * @throws \Exception
     */
    public function getAliasObject($k)
    {
        $aliases = $this->getAliases();

        if (isset($aliases[$k])) {
            return $this->uri->getApp()->managers->getByEntityPk($k)->getAliasManager()->find($aliases[$k]);
        }

        return false;
    }
}