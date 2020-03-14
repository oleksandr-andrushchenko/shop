<?php

namespace SNOWGIRL_SHOP\Entity\Attribute;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_SHOP\Entity\Attribute;

/**
 * Class Value
 *
 * @method \SNOWGIRL_SHOP\Manager\Attribute\Value getManager()
 * @package SNOWGIRL_SHOP\Entity\Attribute
 */
class Value extends Entity
{
    protected static $table = 'attribute_value';
    protected static $pk = 'attribute_value_id';
    protected static $columns = [
        'attribute_value_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'attribute_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => Attribute::class],
        'name' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null],
    ];

    public function setId($v): Entity
    {
        return $this->setAttributeValueId($v);
    }

    public function getId(bool $makeCompositeId = true)
    {
        return $this->getAttributeValueId();
    }

    public function setAttributeValueId($v): Value
    {
        return $this->setRequiredAttr('attribute_value_id', (int)$v);
    }

    public function getAttributeValueId(): int
    {
        return (int)$this->getRawAttr('attribute_value_id');
    }

    public function setAttributeId(int $attributeId): Value
    {
        return $this->setRequiredAttr('attribute_id', $attributeId);
    }

    public function getAttributeId(): int
    {
        return (int)$this->getRawAttr('attribute_id');
    }

    public function setName($v): Value
    {
        return $this->setRequiredAttr('name', trim($v));
    }

    public function getName(): string
    {
        return $this->getRawAttr('name');
    }

    public function setCreatedAt($v)
    {
        return $this->setRawAttr('created_at', self::normalizeTime($v));
    }

    public function getCreatedAt(bool $datetime = false)
    {
        return $datetime ? self::timeToDatetime($this->getRawAttr('created_at')) : $this->getRawAttr('created_at');
    }

    public function setUpdatedAt($v)
    {
        return $this->setRawAttr('updated_at', self::normalizeTime($v, true));
    }

    public function getUpdatedAt(bool $datetime = false)
    {
        return $datetime ? self::timeToDatetime($this->getRawAttr('updated_at')) : $this->getRawAttr('updated_at');
    }
}