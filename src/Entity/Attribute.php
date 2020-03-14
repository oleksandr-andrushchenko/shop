<?php

namespace SNOWGIRL_SHOP\Entity;

use SNOWGIRL_CORE\Entity;

/**
 * Class Attribute
 *
 * @method \SNOWGIRL_SHOP\Manager\Attribute getManager()
 * @package SNOWGIRL_SHOP\Entity
 */
class Attribute extends Entity
{
    protected static $table = 'attribute';
    protected static $pk = 'attribute_id';
    protected static $columns = [
        'attribute_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'name' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'display_name' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'category_id' => ['type' => self::COLUMN_INT, 'default' => null, 'entity' => Category::class],
        'is_mva' => ['type' => self::COLUMN_INT, 'default' => 0],
        'is_active' => ['type' => self::COLUMN_INT, 'default' => 0],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null],
    ];

    public function setId($v): Entity
    {
        return $this->setAttributeId($v);
    }

    public function getId(bool $makeCompositeId = true)
    {
        return $this->getAttributeId();
    }

    public function setAttributeId($v): Attribute
    {
        return $this->setRequiredAttr('attribute_id', (int)$v);
    }

    public function getAttributeId(): int
    {
        return (int)$this->getRawAttr('attribute_id');
    }

    public function setName($v): Attribute
    {
        return $this->setRequiredAttr('name', trim($v));
    }

    public function getName(): string
    {
        return $this->getRawAttr('name');
    }

    public function setDisplayName($v): Attribute
    {
        return $this->setRequiredAttr('display_name', trim($v));
    }

    public function getDisplayName(): string
    {
        return $this->getRawAttr('display_name');
    }

    public function setCategoryId(int $categoryId = null): Attribute
    {
        return $this->setRawAttr('category_id', $categoryId);
    }

    public function getCategoryId(): ?int
    {
        return ($tmp = $this->getRawAttr('category_id')) ? (int)$tmp : null;
    }

    public function setIsMva($v): Attribute
    {
        return $this->setRawAttr('is_mva', $v ? 1 : 0);
    }

    public function getIsMva(): int
    {
        return (int)$this->getRawAttr('is_mva');
    }

    public function isMva(): bool
    {
        return 1 == $this->getIsMva();
    }

    public function setIsActive($v): Attribute
    {
        return $this->setRawAttr('is_active', $v ? 1 : 0);
    }

    public function getIsActive(): int
    {
        return (int)$this->getRawAttr('is_active');
    }

    public function isActive(): bool
    {
        return 1 == $this->getIsActive();
    }

    public function setCreatedAt($v): Attribute
    {
        return $this->setRawAttr('created_at', self::normalizeTime($v));
    }

    public function getCreatedAt(bool $datetime = false)
    {
        return $datetime ? self::timeToDatetime($this->getRawAttr('created_at')) : $this->getRawAttr('created_at');
    }

    public function setUpdatedAt($v): Attribute
    {
        return $this->setRawAttr('updated_at', self::normalizeTime($v, true));
    }

    public function getUpdatedAt(bool $datetime = false)
    {
        return $datetime ? self::timeToDatetime($this->getRawAttr('updated_at')) : $this->getRawAttr('updated_at');
    }
}