<?php

namespace SNOWGIRL_SHOP\Entity;

use SNOWGIRL_SHOP\Entity\Item\Attr;

class Size extends Attr
{
    protected static $table = 'size';
    protected static $pk = 'size_id';

    protected static $columns = [
        'size_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'name' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::SEARCH_DISPLAY, self::REQUIRED],
        //@todo replace search by name with search by hash in all places..
        'name_hash' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'uri' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'rating' => ['type' => self::COLUMN_INT, 'default' => 0],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v)
    {
        return $this->setSizeId($v);
    }

    public function getId($makeCompositeId = true)
    {
        return $this->getSizeId();
    }

    public function setSizeId($v)
    {
        return $this->setRequiredAttr('size_id', (int)$v);
    }

    public function getSizeId()
    {
        return (int)$this->getRawAttr('size_id');
    }

    public function setName($v)
    {
        return $this->setRequiredAttr('name', $v);
    }

    public function getName()
    {
        return $this->getRawAttr('name');
    }

    public function setNameHash($v)
    {
        return $this->setRequiredAttr('name_hash', self::normalizeHash($v));
    }

    public function getNameHash()
    {
        return $this->getRawAttr('name_hash');
    }

    public function setRating($v)
    {
        return $this->setRawAttr('rating', (int)$v);
    }

    public function getRating()
    {
        return (int)$this->getRawAttr('rating');
    }

    public function setCreatedAt($v)
    {
        return $this->setRawAttr('created_at', self::normalizeTime($v));
    }

    public function getCreatedAt($datetime = false)
    {
        $v = $this->getRawAttr('created_at');
        return $datetime ? self::timeToDatetime($v) : $v;
    }

    public function setUpdatedAt($v)
    {
        return $this->setRawAttr('updated_at', self::normalizeTime($v, true));
    }

    public function getUpdatedAt($datetime = false)
    {
        $v = $this->getRawAttr('updated_at');
        return $datetime ? self::timeToDatetime($v) : $v;
    }
}