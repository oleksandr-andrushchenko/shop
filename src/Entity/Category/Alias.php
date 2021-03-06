<?php

namespace SNOWGIRL_SHOP\Entity\Category;

use SNOWGIRL_CORE\Entity;

class Alias extends \SNOWGIRL_SHOP\Entity\Item\Attr\Alias
{
    protected static $table = 'category_alias';
    protected static $pk = 'category_alias_id';

    protected static $columns = [
        'category_alias_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'category_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'name' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::SEARCH_DISPLAY, self::REQUIRED],
        'title' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'breadcrumb' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'uri' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v): Entity
    {
        return $this->setCategoryAliasId($v);
    }

    public function getId(bool $makeCompositeId = true)
    {
        return $this->getCategoryAliasId();
    }

    public function setCategoryAliasId($v)
    {
        return $this->setRequiredAttr('category_alias_id', (int)$v);
    }

    public function getCategoryAliasId()
    {
        return (int)$this->getRawAttr('category_alias_id');
    }

    public function setCategoryId($v)
    {
        return $this->setRequiredAttr('category_id', (int)$v);
    }

    public function getCategoryId()
    {
        return (int)$this->getRawAttr('category_id');
    }

    public function setName($v)
    {
        return $this->setRequiredAttr('name', static::normalizeText($v));
    }

    public function getName()
    {
        return $this->getRawAttr('name');
    }

    public function setTitle($v)
    {
        return $this->setRawAttr('title', ($v = trim($v)) ? $v : null);
    }

    public function getTitle()
    {
        return $this->getRawAttr('title');
    }

    public function setBreadcrumb($v)
    {
        return $this->setRawAttr('breadcrumb', ($v = trim($v)) ? $v : null);
    }

    public function getBreadcrumb()
    {
        return $this->getRawAttr('breadcrumb');
    }

    public function setUri($v)
    {
        return $this->setRequiredAttr('uri', $this->normalizeUri($v));
    }

    public function getUri()
    {
        return $this->getRawAttr('uri');
    }

    public function setCreatedAt($v)
    {
        return $this->setRawAttr('created_at', self::normalizeTime($v));
    }

    public function getCreatedAt($datetime = false)
    {
        return $datetime ? self::timeToDatetime($this->getRawAttr('created_at')) : $this->getRawAttr('created_at');
    }

    public function setUpdatedAt($v)
    {
        return $this->setRawAttr('updated_at', self::normalizeTime($v, true));
    }

    public function getUpdatedAt($datetime = false)
    {
        return $datetime ? self::timeToDatetime($this->getRawAttr('updated_at')) : $this->getRawAttr('updated_at');
    }
}