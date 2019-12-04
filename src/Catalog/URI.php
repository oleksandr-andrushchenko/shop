<?php

namespace SNOWGIRL_SHOP\Catalog;

use SNOWGIRL_CORE\App;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Service\Logger;
use SNOWGIRL_SHOP\App\Console;
use SNOWGIRL_SHOP\App\Web;
use SNOWGIRL_SHOP\Entity\Brand;
use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttr;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Helper\Strings;
use SNOWGIRL_SHOP\Entity\Vendor;
use SNOWGIRL_SHOP\Manager\Item\Attr as AttrManager;

class URI
{
    const CATALOG = 'catalog';
    const SALES = 'sales';
    const SPORT = 'sport';
    const SIZE_PLUS = 'size-plus';
    //@todo CHEEP...
    const CHEEP = 'cheep';//price asc
    //@todo EXPENSIVE...
    const EXPENSIVE = 'expensive';//price desc

//    const QUERY = 'query';
    const PRICE_FROM = 'price_from';
    const PRICE_TO = 'price_to';

    const ORDER = 'sort';
    const PER_PAGE = 'show';
    const PAGE_NUM = 'page';

    const EVEN_NOT_STANDARD_PER_PAGE = 'even_not_standard_per_page';

    //defined dynamic
    const OUTPUT_DEFINED = 0;
    //prev + safe dynamic
    const OUTPUT_DEFINED_SAFE = 1;
    //prev + all extra dynamic
    const OUTPUT_FULL = 2;

    /** @var App */
    protected static $app;

    protected $normalizedParams;
    protected $deNormalizedParams;
    protected $domain;
    protected $map;

    /** @var Entity[] */
    protected static $componentsPkToClass;
    protected static $filterParams;

    const VIEW_PARAMS = [
        self::ORDER,
        self::PER_PAGE,
        self::PAGE_NUM,
        self::EVEN_NOT_STANDARD_PER_PAGE
    ];

    const DEFINED_PARAMS = [
//        self::QUERY,
        self::PRICE_FROM,
        self::PRICE_TO,
        self::ORDER,
        self::PER_PAGE,
        self::PAGE_NUM
    ];

    const SAFE_PARAMS = [];

    protected static $attrPathParams;

    const TYPE_PARAMS = [
        self::SPORT,
        self::SIZE_PLUS,
        self::SALES
    ];

    const NON_ATTR_PATH_PARAMS = self::TYPE_PARAMS;

    protected static $pathParams;
    protected static $addUriPrefix;

    public static function setApp(App $app)
    {
        /** @var App $app */
        self::$app = $app;

        $components = $app->managers->catalog->getComponentsOrderByRdbmsKey();
        self::$componentsPkToClass = Arrays::mapByKeyMaker($components, function ($entity) {
            /** @var Entity $entity */
            return $entity::getPk();
        });

        //@todo remove non format (config) params...
        self::$attrPathParams = array_keys(self::$componentsPkToClass);

        self::$filterParams = array_merge(self::$attrPathParams, [
            //@todo cities, delivery or cities & delivery....
            self::SPORT,
            self::SIZE_PLUS,
            self::SALES,
//            self::QUERY,
            self::PRICE_FROM,
            self::PRICE_TO
        ]);

        self::$pathParams = array_merge(self::$attrPathParams, self::NON_ATTR_PATH_PARAMS);
        self::$addUriPrefix = !!self::$app->config->catalog->add_uri_prefix(false);
    }

    public static function addUriPrefix()
    {
        return self::$addUriPrefix;
    }

    public static function getPathParams()
    {
        return self::$pathParams;
    }

    public function __construct(array $params = [], $domain = false)
    {
        $this
            ->setDomain($domain)
            ->setMap([
                'filter' => self::$filterParams,
                'view' => self::VIEW_PARAMS,
                'attr' => self::$attrPathParams,
                'defined' => self::DEFINED_PARAMS,
                'type' => self::TYPE_PARAMS,
                'safe' => self::SAFE_PARAMS
            ])
            ->setOutput([])
            ->setParams($params);
    }

//    protected $aliases;
//
//    public function setAliases(array $v)
//    {
//        $this->aliases = $v;
//        return $this;
//    }

    public function setDomain($domain)
    {
        $this->domain = $domain;
        return $this;
    }

    public function setMap($map)
    {
        $this->map = $map;
        return $this;
    }

    public function setOutput(array $output)
    {
        $this->output = $output;
        return $this;
    }

    public function setParams(array $params)
    {
        $this->normalizedParams = $this->getNormalizedParams($params);
        $this->dropCache();

        return $this;
    }

    protected function getNormalizedParams(array $params)
    {
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                if (!$v) {
                    unset($params[$k]);
                } else {
                    $params[$k] = array_values(array_filter($v, function ($i) {
                        return null !== $i;
                    }));
                }
            } elseif (null === $v) {
                unset($params[$k]);
            } else {
                $params[$k] = [$v];
            }
        }

        return $params;
    }

    public function __clone()
    {
        $this->dropCache();
    }

    public static function getPagePath(array $params = [], $array = false)
    {
        $output = [
            'vendor',
            'country',
            'season',
            'category',
            self::SPORT,
            'tag',
            self::SIZE_PLUS,
            'brand',
            'color',
            'material',
            'size',
            self::SALES
        ];

        if ($array) {
            return $output;
        }

        return Strings::replaceBracketsParams(implode('/', array_map(function ($param) {
            return '{' . $param . '}';
        }, $output)), $params);
    }

    protected $isCatalogPage;

    /**
     * @todo check and if need - fix
     * @return bool
     */
    public function isCatalogPage()
    {
        if (null !== $this->isCatalogPage) {
            return $this->isCatalogPage;
        }

        $params = $this->getParams();

        if (0 == count(array_intersect(array_keys($params), self::$pathParams))) {
            return $this->isCatalogPage = false;
        }

        foreach (self::$attrPathParams as $k) {
            if (isset($params[$k]) && is_array($params[$k])) {
                return $this->isCatalogPage = false;
            }
        }

        return $this->isCatalogPage = true;
    }

    /**
     * @param array $params
     * @param bool  $aliases
     * @param int   $mode
     *
     * @return array|mixed|string
     * @throws \Exception
     */
    public function getPathByParams(array &$params, $aliases = false, $mode = self::OUTPUT_DEFINED)
    {
        $attrPathParams = array_filter($params, function ($k) {
            return in_array($k, self::$attrPathParams);
        }, ARRAY_FILTER_USE_KEY);

        $nonAttrPathParams = array_filter($params, function ($k) {
            return in_array($k, self::NON_ATTR_PATH_PARAMS);
        }, ARRAY_FILTER_USE_KEY);

        if ($pathParams = array_merge($attrPathParams, $nonAttrPathParams)) {
            $isPage = true;

            foreach ($attrPathParams as $pageId) {
                if (is_array($pageId)) {
                    $isPage = false;
                    break;
                }
            }
        } else {
            $isPage = false;
        }

        // if page - pop them from params, for path creation purposes
        if ($isPage) {
            $params = array_diff_key($params, $pathParams);
        }

        // if filter - filter params
        if (self::OUTPUT_DEFINED == $mode) {
            $params = array_filter($params, function ($k) use ($pathParams, $isPage) {
                return (!$isPage && isset($pathParams[$k])) || in_array($k, self::DEFINED_PARAMS);
            }, ARRAY_FILTER_USE_KEY);
        } elseif (self::OUTPUT_DEFINED_SAFE == $mode) {
            $params = array_filter($params, function ($k) use ($pathParams, $isPage) {
                return (!$isPage && isset($pathParams[$k])) || (in_array($k, self::DEFINED_PARAMS) || in_array($k, self::SAFE_PARAMS));
            }, ARRAY_FILTER_USE_KEY);
        }

        // if not a page - return default path
        if (!$isPage) {
            return self::CATALOG;
        }

        $tmp = [];

        //@todo order...

        $aliases = $aliases ? $this->getSRC()->getAliases() : [];

        foreach ($attrPathParams as $k => $v) {
            /** @var AttrManager $manager */
            $manager = self::$app->managers->getByEntityPk($k);
            $table = $manager->getEntity()->getTable();

            if (isset($aliases[$k])) {
                $v = $aliases[$k];

                if ($v && $entity = $manager->getAliasManager()->find($v)) {
                    $tmp[$table] = $entity->get('uri');
                } else {
                    self::$app->services->logger->make($table . '[id=' . $v . '] is not exists', Logger::TYPE_ERROR);
                }
            } else {
                $table = $manager->getEntity()->getTable();

                if ($v && $entity = $manager->find($v)) {
                    $tmp[$table] = $entity->get('uri');
                } else {
                    self::$app->services->logger->make($table . '[id=' . $v . '] is not exists', Logger::TYPE_ERROR);
                }
            }
        }

        foreach ($nonAttrPathParams as $k => $v) {
            $tmp[$k] = $k;
        }

        $uri = self::getPagePath($tmp);
        $uri = explode('/', $uri);
        $uri = array_filter($uri, function ($i) {
            return $i && '{' !== $i[0];
        });
        $uri = implode('/', $uri);

        return $uri;
    }

    protected function dropCache()
    {
        $this->deNormalizedParams = null;
        $this->output = [];
    }

    public function getParams()
    {
        if (null === $this->deNormalizedParams) {
            $params = $this->normalizedParams;

            foreach ($params as $k => $v) {
                $v = is_array($v) ? $v : [$v];
                $v = array_values(array_filter($v, function ($i) {
                    return null !== $i;
                }));

                if (1 === ($s = count($v))) {
                    $params[$k] = $v[0];
                } elseif (0 === $s) {
                    unset($params[$k]);
                }
            }

            $this->deNormalizedParams = $params;
        }

        return $this->deNormalizedParams;
    }

    public function getParamsArray()
    {
        $output = array_merge(self::$filterParams, self::VIEW_PARAMS);
        $output = array_combine($output, array_fill(0, count($output), null));
        $output = array_merge($output, $this->getParams());

        return $output;
    }

    public function getParamsByNames($names)
    {
        $names = is_array($names) ? $names : [$names];

        return array_filter($this->getParams(), function ($k) use ($names) {
            return in_array($k, $names);
        }, ARRAY_FILTER_USE_KEY);
    }

    public function getOrParamsKeysByNames($names)
    {
        return array_keys($this->getParamsByNames($names));
    }

    public function getParamsByTypes($types)
    {
        $types = is_array($types) ? $types : [$types];

        return array_filter($this->getParams(), function ($k) use ($types) {
            $ok = false;

            foreach ($types as $type) {
                if (in_array($k, $this->map[$type])) {
                    $ok = true;
                    break;
                }
            }

            return $ok;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @todo normalize value...
     *
     * @param $k
     * @param $v
     *
     * @return URI
     */
    public function set($k, $v = 1)
    {
        $this->normalizedParams[$k] = $v;
        $this->dropCache();
        return $this;
    }

    public function _unset($k)
    {
//        unset($this->normalizedParams[$k]);
//        $this->dropCache();
//        return $this;
        return $this->set($k, null);
    }

    public function get($key, $default = null)
    {
        $tmp = $this->getParams();
        return $tmp[$key] ?? $default;
    }

    public function push($k, $v)
    {
        if (!isset($this->normalizedParams[$k])) {
            $this->normalizedParams[$k] = [];
        }

        $this->normalizedParams[$k][] = $v;
        $this->dropCache();
        return $this;
    }

    public function pop($k, $v)
    {
        if (!isset($this->normalizedParams[$k])) {
            $this->normalizedParams[$k] = [];
        }

        $this->normalizedParams[$k] = array_diff($this->normalizedParams[$k], [$v]);
        $this->dropCache();
        return $this;
    }

    public function hasParam($k, $v)
    {
        return in_array($v, $this->normalizedParams[$k] ?? []);
    }

    /**
     * @param            $k
     * @param            $v
     * @param bool|false $isWas
     *
     * @return URI
     */
    public function inverse($k, $v, &$isWas = false)
    {
        $isWas = $this->hasParam($k, $v);

        if ($isWas) {
            $this->pop($k, $v);
        } else {
            $this->push($k, $v);
        }

        return $this;
    }

    protected static $src = [];

    /**
     * @return SRC
     */
    public function getSRC()
    {
        $key = $this->getCacheKey();

        if (isset(self::$src[$key])) {
            return self::$src[$key];
        }

        self::$src[$key] = new SRC($this, [Brand::class, Vendor::class]);

        return self::$src[$key];
    }

    protected $seo;

    /**
     * @return SEO
     */
    public function getSEO()
    {
        return $this->seo ?: $this->seo = new SEO($this);
    }

    /**
     * @todo optimize... (use Attribute::getObjectsByUri for example)
     *
     * @param $param
     * @param $value
     *
     * @return null|ItemAttr|Entity
     */
    public static function getPageComponentByParam($param, $value)
    {
        if (isset(self::$componentsPkToClass[$param])) {
            return self::$app->managers
                ->getByEntityClass(self::$componentsPkToClass[$param])
                ->find($value);
        }

        return null;
    }

    /**
     * @return URI
     */
    public function copy()
    {
        return new self($this->normalizedParams);
//        return new static($this->getParamsArray());
    }

    protected $cacheKey;

    protected function getCacheKey()
    {
        return $this->cacheKey ?: $this->cacheKey = md5(serialize($this->getParams()));
    }

    protected $output;

    /**
     * @param int  $mode
     * @param bool $aliases
     * @param bool $isNoFollow
     *
     * @return mixed
     * @throws \Exception
     */
    public function output($mode = self::OUTPUT_DEFINED, $aliases = false, &$isNoFollow = false)
    {
        if (isset($this->output[$mode])) {
            $isNoFollow = false !== strpos($this->output[$mode], '?');
            return $this->output[$mode];
        }

        $params = $this->getParams();
        $params['uri'] = $this->getPathByParams($params, $aliases, $mode);

        $this->output[$mode] = self::$app->router->makeLink('catalog', $params, $this->domain);
        return $this->output($mode, $aliases, $isNoFollow);
    }

    /**
     * @return Web|Console
     */
    public function getApp()
    {
        return self::$app;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function __toString()
    {
        return $this->output();
    }
}

URI::setApp(App::$instance);