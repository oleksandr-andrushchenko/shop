<?php

namespace SNOWGIRL_SHOP\Entity\Item;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_SHOP\Entity\Item;

class Archive extends Entity
{
    public static function getTable(): string
    {
        return 'item_archive';
    }

    public static function getPk()
    {
        return Item::getPk();
    }

    public static function getColumns(): array
    {
        return array_merge(Item::getColumns(), [
            'tag_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
            'color_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
            'material_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
            'size_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
            'season_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
            'image_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
        ]);
    }
}