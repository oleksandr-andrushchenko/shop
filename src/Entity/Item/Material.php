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
 * Class Material
 * @package SNOWGIRL_SHOP\Entity\Item
 */
class Material extends Entity
{
    protected static $table = 'item_material';
    protected static $pk = ['item_id', 'material_id'];

    protected static $columns = [
        'item_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'material_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => 'SNOWGIRL_SHOP\Entity\Material']
    ];

    protected static $indexes = [
        'ix_material' => ['material_id']
    ];

    public function setItemId($v)
    {
        return $this->setRequiredAttr('item_id', (int)$v);
    }

    public function getItemId()
    {
        return (int)$this->getRawAttr('item_id');
    }

    public function setMaterialId($v)
    {
        return $this->setRequiredAttr('material_id', (int)$v);
    }

    public function getMaterialId()
    {
        return (int)$this->getRawAttr('material_id');
    }
}