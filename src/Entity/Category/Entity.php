<?php

namespace SNOWGIRL_SHOP\Entity\Category;

class Entity extends \SNOWGIRL_CORE\Entity
{
    protected static $table = 'category_entity';
    protected static $pk = 'id';

    protected static $columns = [
        'id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'category_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'entity' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'entity_hash' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'stop_words' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'count' => ['type' => self::COLUMN_INT, 'default' => 0],
        'is_active' => ['type' => self::COLUMN_INT, 'default' => 0]
    ];

    public function setId($v)
    {
        return $this->setRequiredAttr('id', (int)$v);
    }

    public function getId($makeCompositeId = true)
    {
        return (int)$this->getRawAttr('id');
    }

    public function setCategoryId($v)
    {
        return $this->setRequiredAttr('category_id', (int)$v);
    }

    public function getCategoryId()
    {
        return (int)$this->getRawAttr('category_id');
    }

    public function setEntity($v)
    {
        return $this->setRequiredAttr('entity', trim($v));
    }

    public function getEntity()
    {
        return $this->getRawAttr('entity');
    }

    public function setEntityHash($v)
    {
        return $this->setRequiredAttr('entity_hash', self::normalizeHash($v));
    }

    public function getEntityHash()
    {
        return $this->getRawAttr('entity_hash');
    }

    public function setStopWords($v)
    {
        return $this->setRawAttr('stop_words', $v ? (is_array($v) ? implode(',', $v) : $v) : null);
    }

    public function getStopWords($array = false)
    {
        return ($v = $this->getRawAttr('stop_words')) ? ($array ? explode(',', $v) : $v) : null;
    }

    public function setCount($v)
    {
        return $this->setRequiredAttr('count', (int)$v);
    }

    public function getCount()
    {
        return (int)$this->getRawAttr('count');
    }

    public function setIsActive($v)
    {
        return $this->setRawAttr('is_active', $v ? 1 : 0);
    }

    public function getIsActive()
    {
        return (int)$this->getRawAttr('is_active');
    }

    public function isActive()
    {
        return 1 == $this->getIsActive();
    }
}