<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 9/22/18
 * Time: 5:15 PM
 */

namespace SNOWGIRL_SHOP\Entity\Item;

/**
 * Class Redirect
 * @package SNOWGIRL_SHOP\Entity\Item
 */
class Redirect extends \SNOWGIRL_CORE\Entity
{
    protected static $table = 'item_redirect';
    protected static $pk = 'item_redirect_id';
    protected static $isFtdbmsIndex = false;

    protected static $columns = [
        'item_redirect_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'id_from' => ['type' => self::COLUMN_TEXT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'id_to' => ['type' => self::COLUMN_TEXT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v)
    {
        return $this->setItemRedirectId($v);
    }

    public function getId($makeCompositeId = true)
    {
        return $this->getItemRedirectId();
    }

    public function setItemRedirectId($v)
    {
        return $this->setRequiredAttr('item_redirect_id', (int)$v);
    }

    public function getItemRedirectId()
    {
        return (int)$this->getRawAttr('item_redirect_id');
    }

    /**
     * @param $v
     * @return Redirect
     * @throws \SNOWGIRL_CORE\Exception\EntityAttr\Required
     */
    public function setIdFrom($v)
    {
        return $this->setRequiredAttr('id_from', trim($v));
    }

    public function getIdFrom()
    {
        return $this->getRawAttr('id_from');
    }

    public function setIdTo($v)
    {
        return $this->setRequiredAttr('id_to', trim($v));
    }

    public function getIdTo()
    {
        return $this->getRawAttr('id_to');
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