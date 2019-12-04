<?php

namespace SNOWGIRL_SHOP\Entity\Item;

use SNOWGIRL_CORE\Entity;

class Image extends Entity
{
    protected static $table = 'item_image';
    protected static $pk = ['item_id', 'image_id'];

    protected static $columns = [
        'item_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'image_id' => ['type' => self::COLUMN_TEXT, self::IMAGE, self::REQUIRED]
    ];

    public function setItemId($v)
    {
        return $this->setRequiredAttr('item_id', (int)$v);
    }

    public function getItemId()
    {
        return (int)$this->getRawAttr('item_id');
    }

    public function setImageId($v)
    {
        return $this->setRequiredAttr('image_id', trim($v));
    }

    public function getImageId()
    {
        return $this->getRawAttr('image_id');
    }
}