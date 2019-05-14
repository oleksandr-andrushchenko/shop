<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 12/16/16
 * Time: 7:08 PM
 */
namespace SNOWGIRL_SHOP\Entity\Category;

use SNOWGIRL_CORE\Entity;

/**
 * Holds categories children (+category is child of itself)
 * Class Child
 * @package SNOWGIRL_SHOP\Entity\Category
 */
class Child extends Entity
{
    protected static $table = 'category_child';
    protected static $pk = ['category_id', 'child_category_id'];

    protected static $columns = [
        'category_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'child_category_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__],
//            'created_at',
//            'updated_at'
    ];

    public function setCategoryId($v)
    {
        return $this->setRequiredAttr('category_id', (int)$v);
    }

    public function getCategoryId()
    {
        return (int)$this->getRawAttr('category_id');
    }

    public function setChildCategoryId($v)
    {
        return $this->setRequiredAttr('child_category_id', (int)$v);
    }

    public function getChildCategoryId()
    {
        return (int)$this->getRawAttr('child_category_id');
    }
}