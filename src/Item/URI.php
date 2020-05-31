<?php

namespace SNOWGIRL_SHOP\Item;

use SNOWGIRL_CORE\AbstractApp as App;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Router;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Http\HttpApp;

class URI
{
    public const ID = 'id';

    //defined dynamic
    public const OUTPUT_DEFINED = 0;
    //prev + safe dynamic
    public const OUTPUT_DEFINED_SAFE = 1;
    //prev + all extra dynamic
    public const OUTPUT_FULL = 2;

    public const DEFINED_PARAMS = [];
    public const SAFE_PARAMS = [];

    /**
     * @var App|Web|Console
     */
    private static $app;

    private $normalizedParams;

    private $domain;
    private $map;

    private static $pathParams = [self::ID];

    private $src;
    private $seo;
    private $output;


    public function __construct(array $params = [], $domain = false)
    {
        $this->setDomain($domain)
            ->setMap([
                'defined' => self::DEFINED_PARAMS,
                'safe' => self::SAFE_PARAMS
            ])
            ->setOutput([])
            ->setParams($params);
    }

    public static function setApp(App $app)
    {
        /** @var Web|Console $app */
        self::$app = $app;
    }

    public function setDomain($domain): URI
    {
        $this->domain = $domain;

        return $this;
    }

    public function setMap($map): URI
    {
        $this->map = $map;

        return $this;
    }

    public function setOutput(array $output): URI
    {
        $this->output = $output;

        return $this;
    }

    public function setParams(array $params): URI
    {
        $this->normalizedParams = $this->getNormalizedParams($params);
        $this->dropCache();

        return $this;
    }

    private function getNormalizedParams(array $params): array
    {
        return $params;
    }

    private function dropCache(): URI
    {
        $this->output = [];

        return $this;
    }

    public function getParams(): array
    {
        return $this->normalizedParams;
    }

    public function set(string $key, $value): URI
    {
        $this->normalizedParams[$k] = $v;
        $this->dropCache();

        return $this;
    }

    public function get(string $key, $default = null)
    {
        $tmp = $this->getParams();

        return $tmp[$key] ?? $default;
    }

    public function getPathByParams(array &$params, $mode = self::OUTPUT_DEFINED): string
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

        if (!$item = $this->getSRC(self::$app)->getItem()) {
            throw new Exception('no item found by id');
        }

        return self::buildPath($item->getName(), $item->getId());
    }

    public function getSRC(): SRC
    {
        return $this->src ?: $this->src = new SRC($this, [], !empty($this->getApp()->config('catalog.cache')));
    }

    public function getSEO(): SEO
    {
        return $this->seo ?: $this->seo = new SEO($this);
    }

    public static function buildPath($name, $id): string
    {
        return Entity::normalizeUri($name) . '-' . $id;
    }

    public function output($mode = self::OUTPUT_DEFINED, &$isNoFollow = false): string
    {
        if (isset($this->output[$mode])) {
            $isNoFollow = false !== strpos($this->output[$mode], '?');
            return $this->output[$mode];
        }

        $params = $this->getParams();
        $params['uri'] = $this->getPathByParams($params, $mode);

        $this->output[$mode] = $this->getApp()->router->makeLink('item', $params, $this->domain);
        return $this->output($mode, $isNoFollow);
    }

    /**
     * @return HttpApp|ConsoleApp
     */
    public function getApp(): App
    {
        return self::$app;
    }

    public function __toString()
    {
        return $this->output();
    }
}