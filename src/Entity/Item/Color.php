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
 * Class Color
 * @package SNOWGIRL_SHOP\Entity\Item
 */
class Color extends Entity
{
    protected static $table = 'item_color';
    protected static $pk = ['item_id', 'color_id'];

    protected static $columns = [
        'item_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'color_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => 'SNOWGIRL_SHOP\Entity\Color']
    ];

    protected static $indexes = [
        'ix_color' => ['color_id']
    ];

    public function setItemId($v)
    {
        return $this->setRequiredAttr('item_id', (int)$v);
    }

    public function getItemId()
    {
        return (int)$this->getRawAttr('item_id');
    }

    public function setColorId($v)
    {
        return $this->setRequiredAttr('color_id', (int)$v);
    }

    public function getColorId()
    {
        return (int)$this->getRawAttr('color_id');
    }
}