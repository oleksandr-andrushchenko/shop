<?php

namespace SNOWGIRL_SHOP\Entity\Page;

use SNOWGIRL_CORE\Entity;

class Catalog extends Entity
{
    protected static $table = 'page_catalog';
    protected static $pk = 'page_catalog_id';
    protected static $columns = [
        'page_catalog_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'name' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::SEARCH_DISPLAY, self::FTDBMS_FIELD, self::REQUIRED],
        'uri' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'uri_hash' => ['type' => self::COLUMN_TEXT, self::MD5, self::REQUIRED],
        'params' => ['type' => self::COLUMN_TEXT, self::JSON],
        'params_hash' => ['type' => self::COLUMN_TEXT, self::MD5, self::REQUIRED, 'entity' => __CLASS__ . '\Custom'],
        'meta' => ['type' => self::COLUMN_TEXT, self::JSON]
    ];
    protected static $indexes = [
        'uk_uri' => ['uri_hash'],
        'ix_params' => ['params_hash']
    ];

    public function setId($v): Entity
    {
        return $this->setPageCatalogId($v);
    }

    public function getId(bool $makeCompositeId = true)
    {
        return $this->getPageCatalogId();
    }

    public function setPageCatalogId($v)
    {
        return $this->setRequiredAttr('page_catalog_id', (int)$v);
    }

    public function getPageCatalogId()
    {
        return (int)$this->getRawAttr('page_catalog_id');
    }

    /**
     * @param $v
     *
     * @return Entity
     * @throws Entity\EntityException
     */
    public function setName($v)
    {
        return $this->setRequiredAttr('name', self::normalizeText($v));
    }

    public function getName()
    {
        return $this->getRawAttr('name');
    }

    /**
     * @param $v
     *
     * @return Entity
     * @throws Entity\EntityException
     */
    public function setUri($v)
    {
        return $this->setRequiredAttr('uri', $this->normalizeUri($v));
    }

    public function getUri()
    {
        return $this->getRawAttr('uri');
    }

    public function setUriHash($v)
    {
        return $this->setRequiredAttr('uri_hash', self::normalizeHash($v));
    }

    public function getUriHash()
    {
        return $this->getRawAttr('uri_hash');
    }

    public function setParams($v)
    {
        return $this->setRawAttr('params', self::normalizeJson($v, true));
    }

    public function getParams($array = false)
    {
        return $array ? self::jsonToArray($this->getRawAttr('params')) : $this->getRawAttr('params');
    }

    public function addParam($k, $v)
    {
        $params = $this->getParams(true);
        $params[$k] = $v;
        $this->setParams($params);
        return $this;
    }

    public function setParamsHash($v)
    {
        return $this->setRequiredAttr('params_hash', self::normalizeHash($v, true));
    }

    public function getParamsHash()
    {
        return $this->getRawAttr('params_hash');
    }

    public function setMeta($v)
    {
        return $this->setRawAttr('meta', self::normalizeJson($v, true));
    }

    public function getMeta($array = false)
    {
        return $array ? self::jsonToArray($this->getRawAttr('meta')) : $this->getRawAttr('meta');
    }

    public function addMeta($k, $v)
    {
        $meta = $this->getMeta(true);
        $meta[$k] = $v;
        $this->setMeta($meta);
        return $this;
    }

    public function getMetaKey($k, $default = null)
    {
        $meta = $this->getMeta(true);
        return $meta[$k] ?? $default;
    }
}