<?php
/**
 * Created by PhpStorm.
 * User: snowgirlCH
 * Date: 3/28/17
 * Time: 11:01 AM
 */

namespace SNOWGIRL_SHOP\Entity\Category;

/**
 * Class Entity
 * @package SNOWGIRL_SHOP\Entity\Category
 */
class Entity extends \SNOWGIRL_CORE\Entity
{
    protected static $table = 'category_entity';
    protected static $pk = 'id';

    protected static $columns = [
        'id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'category_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__],
//            'lang',
        'value' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'value_hash' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'count' => ['type' => self::COLUMN_INT, self::REQUIRED],
        'is_active' => ['type' => self::COLUMN_INT, 'default' => 0],
//            'created_at',
//            'updated_at'
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

    public function setValue($v)
    {
        return $this->setRequiredAttr('value', trim($v));
    }

    public function getValue()
    {
        return $this->getRawAttr('value');
    }

    public function setValueHash($v)
    {
        return $this->setRequiredAttr('value_hash', trim($v));
    }

    public function getValueHash()
    {
        return $this->getRawAttr('value_hash');
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