<?php
/**
 * Created by JetBrains PhpStorm.
 * User: snowgirl
 * Date: 30.01.16
 * Time: 00:18
 * To change this template use File | Settings | File Templates.
 */
namespace SNOWGIRL_SHOP\Entity;

use SNOWGIRL_SHOP\Entity\Item\Attr;

/**
 * Class Country
 * @package SNOWGIRL_SHOP\Entity
 */
class Country extends Attr
{
    protected static $table = 'country';
    protected static $pk = 'country_id';

    protected static $columns = [
        'country_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'name' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::SEARCH_DISPLAY, self::REQUIRED],
        'uri' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v)
    {
        return $this->setCountryId($v);
    }

    public function getId($makeCompositeId = true)
    {
        return $this->getCountryId();
    }

    public function setCountryId($v)
    {
        return $this->setRequiredAttr('country_id', (int)$v);
    }

    public function getCountryId()
    {
        return (int)$this->getRawAttr('country_id');
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