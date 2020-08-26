<?php

namespace SNOWGIRL_SHOP\Manager\Item;

use SNOWGIRL_CORE\AbstractApp;
use SNOWGIRL_CORE\Console\ConsoleApp;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Http\HttpApp;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Exception;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttrEntity;
use SNOWGIRL_SHOP\Manager\Item\Attr\DataProvider;
use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalogManager;
use SNOWGIRL_SHOP\Entity\Item as ItemEntity;
use SNOWGIRL_SHOP\Manager\Term as TermManager;
use SNOWGIRL_CORE\Entity\Redirect;
use SNOWGIRL_CORE\URI\Manager as UriManager;

/**
 * Class Attr
 * @property HttpApp|ConsoleApp app
 * @method Attr copy($clear = false)
 * @method Attr clear()
 * @method Attr setOrder($orders)
 * @method Attr setLimit($limit)
 * @method DataProvider getDataProvider()
 * @package SNOWGIRL_SHOP\Manager\Item
 */
abstract class Attr extends Manager
{
    protected $checkUri;

    private $useCache;
    private $inStockOnly;
    private $providerName;

    public function __construct(AbstractApp $app)
    {
        parent::__construct($app);

        $this->useCache = !!$app->config('catalog.cache', false);
        $this->inStockOnly = !!$app->configMasterOrOwn('catalog.in_stock_only', false);
        $this->providerName = $app->config('data.provider.src', 'db');
    }

    public function checkUri($checkUri)
    {
        $this->checkUri = $checkUri;
        return $this;
    }

    protected function onInsert(Entity $entity)
    {
        /** @var ItemAttrEntity $entity */

        $output = parent::onInsert($entity);

        if ($uri = $this->findUri($entity)) {
            $entity->setUri($uri);
        } else {
            $output = false;
        }

        if ($entity->hasAttr('name_hash') && !$entity->issetAttr('name_hash')) {
            $entity->set('name_hash', ItemAttrEntity::normalizeHash($entity->getName()));
        }

        return $output;
    }

    protected function onUpdate(Entity $entity)
    {
        /** @var ItemAttrEntity $entity */

        $output = parent::onUpdate($entity);

        if ($entity->isAttrChanged('name')) {
            if ($entity->isAttrChanged('uri') && ($entity->getPrevAttr('uri') == $entity->normalizeUri($entity->getPrevAttr('name')))) {
                $entity->setUri($entity->getName());
            } elseif ($entity->getUri() == $entity->normalizeUri($entity->getPrevAttr('name'))) {
                $entity->setUri($entity->getName());
            }
        }

        if ($entity->isAttrChanged('uri')) {
            if ($uri = $this->findUri($entity)) {
                $entity->setUri($uri);
            } else {
                $output = false;
            }
        }

        if ($entity->hasAttr('name_hash') && $entity->isAttrChanged('name')) {
            $entity->set('name_hash', ItemAttrEntity::normalizeHash($entity->getName()));
        }

        return $output;
    }

    protected function findUri(Entity $entity)
    {
        /** @var ItemAttrEntity $entity */

        $vars = [];

        if ($tmp = $entity->getUri()) {
            //check existing first
            $vars[] = $tmp;
        }

        if ($tmp = $entity->normalizeUri($entity->getName())) {
            $vars[] = $tmp;
            $vars[] = $tmp . '-' . $entity->getTable();
            $vars[] = $entity->getTable() . '-' . $tmp;
        }

        if ($this->checkUri) {
            if (0 < count($vars)) {
                $manager = new UriManager($this->app);
                $components = $this->app->managers->catalog->getComponentsOrderByDbKey();

                foreach ($vars as $var) {
                    if (!$manager->getEntitiesBySlug($var, $components)) {
                        return $var;
                    }
                }
            }

            $this->app->container->logger->debug(__METHOD__ . ': can\'t find uri: ' . var_export($entity, true));
        } elseif (isset($vars[0])) {
            return $vars[0];
        }

        return null;
    }

    /**
     * @param Entity $entity
     * @return bool
     * @throws Entity\EntityException
     */
    protected function onUpdated(Entity $entity)
    {
        /** @var ItemAttrEntity $entity */

        $output = parent::onUpdated($entity);

        if ($entity->isAttrChanged('uri')) {
            $output = $output && $this->app->managers->redirects->save((new Redirect)
                    ->setUriFrom($entity->getPrevAttr('uri'))
                    ->setUriTo($entity->getUri()));
        }

        return $output;
    }

    /**
     * @param Entity $entity
     * @return bool
     * @throws Exception
     */
    protected function onDelete(Entity $entity)
    {
        $output = parent::onDelete($entity);

        if ($this->isSva()) {
            if ($this->app->managers->items->clear()
                ->setWhere([$this->entity->getPk() => $entity->getId()])
                ->setLimit(1)
                ->getArray()
            ) {
                throw new Exception('there are items with this ' . $this->entity->getTable());
            }
        }

        if ($this->app->managers->catalog->clear()->getObjectByParams([$this->entity->getPk() => $entity->getId()])) {
            throw new Exception('there are catalog pages with this ' . $this->entity->getTable());
        }

        if ($this->isMva()) {
            $this->getMvaLinkManager()->deleteMany([
                $this->entity->getPk() => $entity->getId()
            ]);
        }

        return $output;
    }

    protected function getProviderKeys(): array
    {
        return [
            'attr'
        ];
    }

    protected function getProviderClasses(): array
    {
        return [
            __CLASS__
        ];
    }

    public function getItemsCountsListByUriCacheKey(URI $uri, string $query = null, bool $prefix = false): string
    {
        return implode('-', [
            $this->entity->getTable(),
            $this->inStockOnly,
            $this->providerName,
            md5(serialize([
                $this->getParams(),
                $uri->getParamsByTypes('filter'),
                $query,
                $prefix,
            ])),
            'ids'
        ]);
    }

    protected function getFiltersCountsByUri(URI $uri, string $query = null, bool $prefix = false)
    {
        $fn = function ($uri, $query, $prefix) {
            $output = [];

            $pk = $this->entity->getPk();

            foreach ($this->getDataProvider()->getFiltersCountsByUri($uri, $query, $prefix) as $i) {
                $output[$i[$pk]] = (int) $i['cnt'];
            }

            return $output;
        };

        if ($this->useCache) {
            $key = $this->getItemsCountsListByUriCacheKey($uri, $query, $prefix);

            if (!$this->app->container->cache->has($key, $output)) {
                $this->app->container->cache->set($key, $output = $fn($uri, $query, $prefix));
            }

            return $output;
        }

        return $fn($uri, $query, $prefix);
    }

    /**
     * @param URI $uri
     * @param bool|true $itemsCounts
     * @return Entity[]|ItemAttrEntity[]
     */
    public function getObjectsByUri(URI $uri, $itemsCounts = true)
    {
        $counts = $this->getFiltersCountsByUri($uri);

        return $this->populateList(array_keys($counts), [], $itemsCounts ? function ($object) use ($counts) {
            /** @var Entity $object */
            $object->setRawVar('items_count', $counts[$object->getId()]);
        } : null);
    }

    /**
     * @param URI $uri
     * @param string $query
     * @param bool $itemsCounts
     * @param bool $prefix
     * @return Entity[]|ItemAttrEntity[]
     */
    public function getObjectsByUriAndQuery(URI $uri, string $query, bool $itemsCounts = true, bool $prefix = false): array
    {
        $counts = $this->getFiltersCountsByUri($uri, $query, $prefix);

        return $this->populateList(array_keys($counts), [], $itemsCounts ? function ($object) use ($counts) {
            /** @var Entity $object */
            $object->setRawVar('items_count', $counts[$object->getId()]);
        } : null);
    }

    /**
     * @param array $itemId
     * @param bool|false $names
     * @return array
     */
    public function getMva(array $itemId, &$names = false)
    {
        $output = [];

        $itemPk = ItemEntity::getPk();
        $attrPk = $this->entity->getPk();

        $items = $this->getMvaLinkManager()->setWhere([$itemPk => $itemId])->getArrays();

        if (null === $names) {
            $attrId = [];

            foreach ($items as $item) {
                if (!isset($output[$item[$itemPk]])) {
                    $output[$item[$itemPk]] = [];
                }

                $output[$item[$itemPk]][] = $item[$attrPk];
                $attrId[] = $item[$attrPk];
            }

            $tmp = $this->copy(true)
                ->setColumns([$attrPk, 'name'])
                ->setWhere([$attrPk => array_unique($attrId)])
                ->getArrays();

            $names = [];

            foreach ($tmp as $item) {
                $names[$item[$attrPk]] = $item['name'];
            }
        } else {
            foreach ($items as $item) {
                if (!isset($output[$item[$itemPk]])) {
                    $output[$item[$itemPk]] = [];
                }

                $output[$item[$itemPk]][] = $item[$attrPk];
            }
        }

        return $output;
    }

    public function getLink(Entity $entity, array $params = [], $domain = false)
    {
        /** @var ItemAttrEntity $entity */
        $tmp = ['uri' => $entity->getUri()];
        $tmp = array_merge($tmp, $params);

        return $this->app->router->makeLink('catalog', $tmp, $domain);
    }

    public static function makeLinkTableNameByEntityClass($entityClass)
    {
        /** @var Entity $entityClass */
        return 'item_' . $entityClass::getTable();
    }

    public static function makeLinkEntityClassByAttrEntityClass($attrEntityClass)
    {
        return str_replace('Entity', 'Entity\Item', $attrEntityClass);
    }

    public static function makeMvaLinkEntityName()
    {
        return str_replace('Entity', 'Entity\Item', static::getEntityClass());
    }

    public function isMva()
    {
        return in_array(get_class($this->entity), PageCatalogManager::getMvaComponents());
    }

    public function isSva()
    {
        return !$this->isMva();
    }

    public function getMvaLinkManager()
    {
        return $this->app->managers->getByEntityClass(static::makeMvaLinkEntityName());
    }

    public function getAliasManager()
    {
        return $this->app->managers->getByEntityClass(static::getEntityClass() . '\\Alias');
    }

    /**
     * @return bool|Manager|TermManager
     */
    public function getTermsManager()
    {
        $class = get_called_class() . '\\Term';

        if (!class_exists($class)) {
            return false;
        }

        return $this->app->managers->get($class);
    }
}