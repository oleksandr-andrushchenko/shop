<?php

namespace SNOWGIRL_SHOP\Entity\Item;

use SNOWGIRL_CORE\Entity;

class Redirect extends \SNOWGIRL_CORE\Entity
{
    protected static $table = 'item_redirect';
    protected static $pk = 'item_redirect_id';

    protected static $columns = [
        'item_redirect_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'id_from' => ['type' => self::COLUMN_TEXT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'id_to' => ['type' => self::COLUMN_TEXT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v): Entity
    {
        return $this->setItemRedirectId($v);
    }

    public function getId(bool $makeCompositeId = true)
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
     *
     * @return \SNOWGIRL_CORE\Entity
     * @throws \SNOWGIRL_CORE\Entity\EntityException
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