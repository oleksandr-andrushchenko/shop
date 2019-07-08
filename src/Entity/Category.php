<?php

namespace SNOWGIRL_SHOP\Entity;

use SNOWGIRL_SHOP\Entity\Item\Attr;

/**
 * Class Category
 * @method static Category factory()
 *
 * @package SNOWGIRL_SHOP\Entity
 */
class Category extends Attr
{
    protected static $table = 'category';
    protected static $pk = 'category_id';

    protected static $columns = [
        'category_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'name' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::SEARCH_DISPLAY, self::REQUIRED],
        'title' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'breadcrumb' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'uri' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'image' => ['type' => self::COLUMN_TEXT, self::IMAGE, 'default' => null],
        'rating' => ['type' => self::COLUMN_INT, 'default' => 0],
        'is_leaf' => ['type' => self::COLUMN_INT, 'default' => 1],
        'parent_category_id' => ['type' => self::COLUMN_INT, 'default' => null, 'entity' => __CLASS__],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v)
    {
        return $this->setCategoryId($v);
    }

    public function getId($makeCompositeId = true)
    {
        return $this->getCategoryId();
    }

    public function setCategoryId($v)
    {
        return $this->setRequiredAttr('category_id', (int)$v);
    }

    public function getCategoryId()
    {
        return (int)$this->getRawAttr('category_id');
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

    public function setImage($v)
    {
        return $this->setRawAttr('image', ($v = trim($v)) ? $v : null);
    }

    /**
     * @return null|string
     */
    public function getImage()
    {
        return ($v = $this->getRawAttr('image')) ? $v : null;
    }

    public function setRating($v)
    {
        return $this->setRawAttr('rating', (int)$v);
    }

    public function getRating()
    {
        return (int)$this->getRawAttr('rating');
    }

    public function setIsLeaf($v)
    {
        return $this->setRawAttr('is_leaf', self::normalizeBool($v));
    }

    public function getIsLeaf()
    {
        return (int)$this->getRawAttr('is_leaf');
    }

    public function isLeaf()
    {
        return 1 == $this->getIsLeaf();
    }

    public function setParentCategoryId($v)
    {
        return $this->setRawAttr('parent_category_id', ($v = (int)$v) ? $v : null);
    }

    public function getParentCategoryId()
    {
        return ($v = (int)$this->getRawAttr('parent_category_id')) ? $v : null;
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