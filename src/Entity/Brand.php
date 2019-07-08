<?php

namespace SNOWGIRL_SHOP\Entity;

use SNOWGIRL_SHOP\Entity\Item\Attr;

class Brand extends Attr
{
    protected static $table = 'brand';
    protected static $pk = 'brand_id';

    protected static $columns = [
        'brand_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'name' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::SEARCH_DISPLAY, self::REQUIRED],
        'uri' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'image' => ['type' => self::COLUMN_TEXT, self::MD5, self::IMAGE, 'default' => null],
        'no_index' => ['type' => self::COLUMN_INT, self::BOOL, 'default' => 0],
        'rating' => ['type' => self::COLUMN_INT, 'default' => 0],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v)
    {
        return $this->setBrandId($v);
    }

    public function getId($makeCompositeId = true)
    {
        return $this->getBrandId();
    }

    public function setBrandId($v)
    {
        return $this->setRequiredAttr('brand_id', (int)$v);
    }

    public function getBrandId()
    {
        return (int)$this->getRawAttr('brand_id');
    }

    public static function _normalizeText($v)
    {
        $tmp = parent::normalizeText($v);

        if (mb_strtoupper($tmp) == $tmp) {
            $tmp = mb_strtolower($tmp);
            $tmp = ucwords($tmp);
        } elseif (mb_strtolower($tmp) == $tmp) {
            $tmp = ucwords($tmp);
        }

        return $tmp;
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

    public function setNoIndex($v)
    {
        return $this->setRawAttr('no_index', $v ? 1 : 0);
    }

    public function getNoIndex()
    {
        return (int)$this->getRawAttr('no_index');
    }

    public function isNoIndex()
    {
        return 1 == $this->getNoIndex();
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