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
 * Class Season
 * @package SNOWGIRL_SHOP\Entity\Item
 */
class Season extends Entity
{
    protected static $table = 'item_season';
    protected static $pk = ['item_id', 'season_id'];

    protected static $columns = [
        'item_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'season_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => 'SNOWGIRL_SHOP\Entity\Season']
    ];

    protected static $indexes = [
        'ix_season' => ['season_id']
    ];

    public function setItemId($v)
    {
        return $this->setRequiredAttr('item_id', (int)$v);
    }

    public function getItemId()
    {
        return (int)$this->getRawAttr('item_id');
    }

    public function setSeasonId($v)
    {
        return $this->setRequiredAttr('season_id', (int)$v);
    }

    public function getSeasonId()
    {
        return (int)$this->getRawAttr('season_id');
    }
}