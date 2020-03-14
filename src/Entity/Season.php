<?php

namespace SNOWGIRL_SHOP\Entity;

use SNOWGIRL_SHOP\Entity\Item\Attr;
use SNOWGIRL_CORE\Entity;

class Season extends Attr
{
    protected static $table = 'season';
    protected static $pk = 'season_id';

    protected static $columns = [
        'season_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'name' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::SEARCH_DISPLAY, self::REQUIRED],
        //@todo replace search by name with search by hash in all places..
        'name_hash' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'uri' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v): Entity
    {
        return $this->setSeasonId($v);
    }

    public function getId(bool $makeCompositeId = true)
    {
        return $this->getSeasonId();
    }

    public function setSeasonId($v)
    {
        return $this->setRequiredAttr('season_id', (int)$v);
    }

    public function getSeasonId()
    {
        return (int)$this->getRawAttr('season_id');
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