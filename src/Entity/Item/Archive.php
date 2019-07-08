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
        'upc' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::FTDBMS_FIELD, self::REQUIRED],
        'partner_link' => ['type' => self::COLUMN_TEXT, self::FTDBMS_ATTR, self::REQUIRED],
        'image' => ['type' => self::COLUMN_TEXT, self::IMAGE, self::FTDBMS_ATTR, self::REQUIRED],
        'price' => ['type' => self::COLUMN_FLOAT, self::REQUIRED, self::FTDBMS_ATTR],
        'old_price' => ['type' => self::COLUMN_FLOAT, self::FTDBMS_ATTR, 'default' => null],
        'entity' => ['type' => self::COLUMN_TEXT, self::FTDBMS_ATTR],
        'description' => ['type' => self::COLUMN_TEXT],
        'category_id' => ['type' => self::COLUMN_INT, self::REQUIRED, self::FTDBMS_ATTR, 'default' => null, 'entity' => __NAMESPACE__ . '\Category'],
        'brand_id' => ['type' => self::COLUMN_INT, self::FTDBMS_ATTR, 'default' => null, 'entity' => __NAMESPACE__ . '\Brand'],
        'country_id' => ['type' => self::COLUMN_INT, self::FTDBMS_ATTR, 'default' => null, 'entity' => __NAMESPACE__ . '\Country'],
        'vendor_id' => ['type' => self::COLUMN_INT, self::FTDBMS_ATTR, self::REQUIRED, 'default' => null, 'entity' => __NAMESPACE__ . '\Vendor'],
        'is_sport' => ['type' => self::COLUMN_INT, self::FTDBMS_ATTR, 'default' => 0],
        'is_size_plus' => ['type' => self::COLUMN_INT, self::FTDBMS_ATTR, 'default' => 0],
        'is_in_stock' => ['type' => self::COLUMN_INT, self::FTDBMS_ATTR, 'default' => 0],
        'created_at' => ['type' => self::COLUMN_TIME, self::FTDBMS_ATTR, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, self::FTDBMS_ATTR, 'default' => null],
        'tag_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'color_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'material_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'size_id' => ['type' => self::COLUMN_TEXT, 'default' => null],
        'season_id' => ['type' => self::COLUMN_TEXT, 'default' => null]
    ];
}