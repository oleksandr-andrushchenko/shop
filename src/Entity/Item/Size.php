<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 4/7/17
 * Time: 1:54 AM
 */
namespace SNOWGIRL_SHOP\Entity\Item;

use SNOWGIRL_CORE\Entity;

/**
 * Class Size
 * @package SNOWGIRL_SHOP\Entity\Item
 */
class Size extends Entity
{
    protected static $table = 'item_size';
    protected static $pk = ['item_id', 'size_id'];

    protected static $columns = [
        'item_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'size_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => 'SNOWGIRL_SHOP\Entity\Size']
    ];

    protected static $indexes = [
        'ix_size' => ['size_id']
    ];

    public function setItemId($v)
    {
        return $this->setRequiredAttr('item_id', (int)$v);
    }

    public function getItemId()
    {
        return (int)$this->getRawAttr('item_id');
    }

    public function setSizeId($v)
    {
        return $this->setRequiredAttr('size_id', (int)$v);
    }

    public function getSizeId()
    {
        return (int)$this->getRawAttr('size_id');
    }
}