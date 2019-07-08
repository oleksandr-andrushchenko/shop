<?php

namespace SNOWGIRL_SHOP\Entity;

use SNOWGIRL_SHOP\Entity\Item\Attr;

/**
 * Class Color
 *
 * @package SNOWGIRL_SHOP\Entity
 * @method \SNOWGIRL_SHOP\Manager\Color getManager()
 */
class Color extends Attr
{
    protected static $table = 'color';
    protected static $pk = 'color_id';

    protected static $columns = [
        'color_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'name' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::SEARCH_DISPLAY, self::REQUIRED],
        //@todo replace search by name with search by hash in all places..
        'name_hash' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'name_multiply' => ['type' => self::COLUMN_TEXT],
        'uri' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'hex' => ['type' => self::COLUMN_TEXT],
        'rating' => ['type' => self::COLUMN_INT, 'default' => 0],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v)
    {
        return $this->setColorId($v);
    }

    public function getId($makeCompositeId = true)
    {
        return $this->getColorId();
    }

    public function setColorId($v)
    {
        return $this->setRequiredAttr('color_id', (int)$v);
    }

    public function getColorId()
    {
        return (int)$this->getRawAttr('color_id');
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

    public function setNameMultiply($v)
    {
        return $this->setRawAttr('name_multiply', trim($v));
    }

    public function getNameMultiply()
    {
        return ($v = trim($this->getRawAttr('name_multiply'))) ? $v : null;
    }

    public function setHex($v)
    {
        return $this->setRawAttr('hex', ($v = trim($v)) ? $v : null);
    }

    public function getHex()
    {
        return ($v = $this->getRawAttr('hex')) ? $v : null;
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