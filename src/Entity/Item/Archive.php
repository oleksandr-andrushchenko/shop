<?php

namespace SNOWGIRL_SHOP\Entity\Item;

class Archive extends \SNOWGIRL_CORE\Entity
{
    protected static $table = 'item_archive';
    protected static $pk = 'item_id';
    protected static $isFtdbmsIndex = false;

    protected static $columns = [
        'item_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'name' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::SEARCH_DISPLAY, self::REQUIRED, self::FTDBMS_FIELD],
        'partner_item_id' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::FTDBMS_FIELD, self::REQUIRED],
        'partner_link' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'image' => ['type' => self::COLUMN_TEXT, self::IMAGE, self::REQUIRED],
        'price' => ['type' => self::COLUMN_FLOAT, self::REQUIRED],
        'old_price' => ['type' => self::COLUMN_FLOAT, 'default' => null],
        'entity' => ['type' => self::COLUMN_TEXT],
        'description' => ['type' => self::COLUMN_TEXT],
        'category_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'default' => null, 'entity' => __NAMESPACE__ . '\Category'],
        'brand_id' => ['type' => self::COLUMN_INT, 'default' => null, 'entity' => __NAMESPACE__ . '\Brand'],
        'country_id' => ['type' => self::COLUMN_INT, 'default' => null, 'entity' => __NAMESPACE__ . '\Country'],
        'vendor_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'default' => null, 'entity' => __NAMESPACE__ . '\Vendor'],
        'is_sport' => ['type' => self::COLUMN_INT, 'default' => 0],
        'is_size_plus' => ['type' => self::COLUMN_INT, 'default' => 0],
        'is_in_stock' => ['type' => self::COLUMN_INT, 'default' => 0],
        'tag_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'color_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'material_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'size_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'season_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'partner_updated_at' => ['type' => self::COLUMN_INT, self::REQUIRED],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
    ];
}