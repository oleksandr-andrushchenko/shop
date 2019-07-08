<?php

namespace SNOWGIRL_SHOP\Manager\Page;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\Catalog\SRC;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalogEntity;
use SNOWGIRL_SHOP\Entity\Page\Catalog\Custom as PageCatalogCustomEntity;

use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttr;

use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Entity\Brand;
use SNOWGIRL_SHOP\Entity\Tag;
use SNOWGIRL_SHOP\Entity\Color;
use SNOWGIRL_SHOP\Entity\Country;
use SNOWGIRL_SHOP\Entity\Vendor;
use SNOWGIRL_SHOP\Entity\Material;
use SNOWGIRL_SHOP\Entity\Size;
use SNOWGIRL_SHOP\Entity\Season;

/**
 * Class Catalog
 *
 * @property PageCatalogEntity $entity
 * @method static PageCatalogEntity getItem($id)
 * @method static Catalog factory($app)
 * @method Catalog copy($clear = false)
 * @method Catalog addWhere($v)
 * @method Catalog clear()
 * @method Catalog setService($service)
 * @method Catalog setLimit($limit)
 * @method PageCatalogEntity[] getObjectsByQuery($query, $column = null)
 * @method PageCatalogEntity find($id)
 * @method PageCatalogEntity getObject()
 * @method PageCatalogEntity[] getObjects($idAsKeyOrKey = null)
 * @method Catalog setWhere($where)
 * @package SNOWGIRL_SHOP\Manager\Page
 */
class Catalog extends Manager
{
    protected function onInsert(Entity $entity)
    {
        /** @var PageCatalogEntity $entity */

        $output = parent::onInsert($entity);

        $entity->setUriHash($this->entity->normalizeHash($entity->getUri()));

        if ($params = $entity->getParams()) {
            $entity->setParamsHash($this->entity->normalizeHash($params));
        }

        return $output;
    }

    protected function onUpdate(Entity $entity)
    {
        /** @var PageCatalogEntity $entity */

        $output = parent::onUpdate($entity);

        if ($entity->isAttrChanged('uri')) {
            $entity->setUriHash($this->entity->normalizeHash($entity->getUri()));
        }

        if ($entity->isAttrChanged('params')) {
            $entity->setParamsHash($this->entity->normalizeHash($entity->getParams()));
        }

        return $output;
    }

    protected function getProviderClasses(): array
    {
        return array_merge([
            __CLASS__
        ], parent::getProviderClasses());
    }

    protected function getProviderKeys(): array
    {
        return [
            'catalog'
        ];
    }

    /**
     * @param array $params
     *
     * @return array|PageCatalogEntity[]
     * @throws \Exception
     */
    public function getObjectsByParams(array $params)
    {
        $tmp = [];

        //synced with pages generation hash order
        foreach (array_merge($this->getComponentsPKs(), URI::TYPE_PARAMS) as $param) {
            if (isset($params[$param])) {
                $tmp[$param] = $params[$param];
            }
        }

        if (0 == count($tmp)) {
            $this->app->services->logger->make('catalog: empty "params"');
            return [];
        }

        $json = $this->entity->normalizeJson($tmp);

        return $this->addWhere(['params_hash' => $this->entity->normalizeHash($json)])
            ->getObjects();
    }

    /**
     * @param array $params
     *
     * @return mixed|null|PageCatalogEntity
     * @throws \Exception
     */
    public function getObjectByParams(array $params)
    {
        $tmp = $this->setLimit(1)->getObjectsByParams($params);
        return $tmp ? $tmp[0] : null;
    }

    /**
     * @param array $params
     *
     * @return bool|mixed|null|Entity|PageCatalogEntity
     * @throws \Exception
     */
    public function findByParams(array $params)
    {
        $key = 'page-by-params-' . md5(serialize($params));

        if (false !== ($output = $this->app->services->mcms->get($key))) {
            return self::makeObjectFromCache($output);
        }

        $output = $this->copy(true)
//            ->setStorage(Manager::SERVICE_FTDBMS_RDBMS)
            ->getObjectByParams($params);

        $this->app->services->mcms->set($key, self::makeObjectFromCache($output));
        return $output;
    }

    public function getObjectsByUri($uri)
    {
        if ($uri instanceof URI) {
            return $this->getObjectsByParams($uri->getParams());
        }

        $uri = implode('/', array_map(function ($uri) {
            return $this->entity->normalizeUri($uri);
        }, explode('/', $uri)));

        if (0 == strlen($uri)) {
            $this->app->services->logger->make('catalog: empty "uri"');
            return [];
        }

        return $this->addWhere(['uri_hash' => $this->entity->normalizeHash($uri)])
            ->getObjects();
    }

    /**
     * @param $uri
     *
     * @return null|PageCatalogEntity
     */
    public function getObjectByUri($uri)
    {
        $tmp = $this->setLimit(1)->getObjectsByUri($uri);
        return $tmp ? $tmp[0] : null;
    }

    /**
     * @param $uri
     *
     * @return Entity|PageCatalogEntity
     */
    public function findByUri($uri)
    {
        $uri = $this->entity->normalizeUri($uri);
        $key = 'page-by-uri-' . str_replace('/', '_', $uri);

        if (false !== ($output = $this->app->services->mcms->get($key))) {
            return self::makeObjectFromCache($output);
        }

        $output = $this->copy(true)
            ->getObjectByUri($uri);

        $this->app->services->mcms->set($key, self::makeObjectFromCache($output));
        return $output;
    }

    public function getLink(Entity $entity, array $params = [], $domain = false)
    {
        /** @var PageCatalogEntity $entity */
        $params['uri'] = $entity->getUri();
        return $this->app->router->makeLink('catalog', $params, $domain);
//        return (string)self::getCatalogUri($entity);
    }

    /**
     * @todo... optimize
     *
     * @param PageCatalogEntity $pageCatalog
     *
     * @return URI
     */
    public static function getCatalogUri(PageCatalogEntity $pageCatalog)
    {
        return new URI(array_filter($pageCatalog->getAttrs(), function ($v, $k) {
            if (!in_array($k, self::getComponentsPKs())) {
                return false;
            }

            return !!$v;
        }, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * !!! keep index's position similar to Rdbms index
     *
     * @return ItemAttr[]|string[]
     */
    public static function getSvaComponents()
    {
        return [
            0 => Category::class,
            2 => Brand::class,
            6 => Country::class,
            8 => Vendor::class
        ];
    }

    /**
     * !!! keep index's position similar to Rdbms index
     *
     * @return ItemAttr[]|string[]
     */
    public static function getMvaComponents()
    {
        return [
            1 => Tag::class,
            3 => Color::class,
            4 => Material::class,
            5 => Size::class,
            7 => Season::class
        ];
    }

    /**
     * !!! do not change order [coz of db table key]
     *
     * @return ItemAttr[]
     */
    public static function getComponentsOrderByRdbmsKey()
    {
        $tmp = self::getSvaComponents() + self::getMvaComponents();
        ksort($tmp);
        return $tmp;
    }

    public function getUriParams(PageCatalogEntity $pageCatalog)
    {
        $output = [];

        $columnsToTypes = array_flip(SRC::getTypesToColumns());
        $pathParams = URI::getPathParams();

        foreach ($pageCatalog->getAttrs() as $k => $v) {
            if (isset($columnsToTypes[$k])) {
                $k = $columnsToTypes[$k];
            }

            if (in_array($k, $pathParams) && ($v > 0)) {
                $output[$k] = $v;
            }
        }

        return $output;
    }

    public static function getComponentsPKs()
    {
        return array_map(function ($component) {
            /** @var ItemAttr $component */
            return $component::getPk();
        }, static::getComponentsOrderByRdbmsKey());
    }

    public static function getComponentsTables()
    {
        return array_map(function ($component) {
            /** @var ItemAttr $component */
            return $component::getTable();
        }, static::getComponentsOrderByRdbmsKey());
    }

    /**
     * @return Entity[]
     */
    public static function getComponentPkToClass()
    {
        return array_combine(self::getComponentsPKs(), self::getComponentsOrderByRdbmsKey());
    }

    public static function getSvaPkToTable()
    {
        return Arrays::mapByKeyValueMaker(static::getSvaComponents(), function ($i, $entity) {
            /** @var string|Entity $entity */
            return [$entity::getPk(), $entity::getTable()];
        });
    }

    public static function getMvaPkToTable()
    {
        return Arrays::mapByKeyValueMaker(static::getMvaComponents(), function ($i, $entity) {
            /** @var string|Entity $entity */
            return [$entity::getPk(), $entity::getTable()];
        });
    }

    /**
     * @param PageCatalogEntity $pageCatalog
     *
     * @return PageCatalogCustomEntity
     */
    public function getPageCatalogCustom(PageCatalogEntity $pageCatalog)
    {
        return $this->getLinked($pageCatalog, 'params_hash');
    }

    public function getCustom(PageCatalogEntity $pageCatalog)
    {
        return $this->getPageCatalogCustom($pageCatalog);
    }

    /**
     * @param PageCatalogEntity $pageCatalog
     *
     * @return PageCatalogCustomEntity
     */
    public function makeCustom(PageCatalogEntity $pageCatalog)
    {
        return new PageCatalogCustomEntity([
            'params_hash' => $pageCatalog->getParamsHash()
        ]);
    }
}