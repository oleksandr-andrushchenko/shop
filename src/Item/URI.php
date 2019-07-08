<?php

namespace SNOWGIRL_SHOP\Item;

use SNOWGIRL_CORE\App;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Exception;

class URI
{
    public const ID = 'id';

    //defined dynamic
    public const OUTPUT_DEFINED = 0;
    //prev + safe dynamic
    public const OUTPUT_DEFINED_SAFE = 1;
    //prev + all extra dynamic
    public const OUTPUT_FULL = 2;

    /** @var App */
    protected static $app;

    protected $normalizedParams;

    protected $domain;
    protected $map;

    public const DEFINED_PARAMS = [];

    public const SAFE_PARAMS = [];

    protected static $pathParams;

    public static function setApp(App $app)
    {
        self::$app = $app;

        self::$pathParams = [self::ID];
    }

    public function __construct(array $params = [], $domain = false)
    {
        $this
            ->setDomain($domain)
            ->setMap([
                'defined' => self::DEFINED_PARAMS,
                'safe' => self::SAFE_PARAMS
            ])
            ->setOutput([])
            ->setParams($params);
    }

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
        return $params;
    }

    protected function dropCache()
    {
        $this->output = [];
    }

    public function getParams()
    {
        return $this->normalizedParams;
    }

    /**
     * @todo normalize value...
     *
     * @param $k
     * @param $v
     *
     * @return URI
     */
    public function set($k, $v)
    {
        $this->normalizedParams[$k] = $v;
        $this->dropCache();
        return $this;
    }

    public function get($key, $default = null)
    {
        $tmp = $this->getParams();
        return $tmp[$key] ?? $default;
    }

    /**
     * @param array $params
     * @param int   $mode
     *
     * @return string
     * @throws Exception
     */
    public function getPathByParams(array &$params, $mode = self::OUTPUT_DEFINED)
    {
        $ids = array_filter($params, function ($k) {
            return in_array($k, self::$pathParams);
        }, ARRAY_FILTER_USE_KEY);

        if ($isPage = isset($ids[self::ID])) {
            $params = array_diff_key($params, $ids);
        }

        if (self::OUTPUT_DEFINED == $mode) {
            $params = array_filter($params, function ($k) use ($ids) {
                return !isset($ids[$k]) && in_array($k, self::DEFINED_PARAMS);
            }, ARRAY_FILTER_USE_KEY);
        } elseif (self::OUTPUT_DEFINED_SAFE == $mode) {
            $params = array_filter($params, function ($k) use ($ids) {
                return !isset($ids[$k]) && (in_array($k, self::DEFINED_PARAMS) || in_array($k, self::SAFE_PARAMS));
            }, ARRAY_FILTER_USE_KEY);
        }

        if (!$isPage) {
            throw new Exception('no path params found');
        }

        if (!$item = $this->getSRC()->getItem()) {
            throw new Exception('no item found by id');
        }

        return self::buildPath($item->getName(), $item->getId());
    }

    protected $src;

    /**
     * @return SRC
     */
    public function getSRC()
    {
        return $this->src ?: $this->src = new SRC($this);
    }

    protected $seo;

    /**
     * @return SEO
     */
    public function getSEO()
    {
        return $this->seo ?: $this->seo = new SEO($this);
    }

    public static function buildPath($name, $id)
    {
        return Entity::normalizeUri($name) . '-' . $id;
    }

    protected $output;

    /**
     * @param int  $mode
     * @param bool $isNoFollow
     *
     * @return mixed
     * @throws Exception
     */
    public function output($mode = self::OUTPUT_DEFINED, &$isNoFollow = false)
    {
        if (isset($this->output[$mode])) {
            $isNoFollow = false !== strpos($this->output[$mode], '?');
            return $this->output[$mode];
        }

        $params = $this->getParams();
        $params['uri'] = $this->getPathByParams($params, $mode);

        $this->output[$mode] = self::$app->router->makeLink('item', $params, $this->domain);
        return $this->output($mode, $isNoFollow);
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function __toString()
    {
        return $this->output();
    }
}

URI::setApp(App::$instance);