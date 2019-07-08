<?php

namespace SNOWGIRL_SHOP\Entity\Item;

use SNOWGIRL_CORE\Entity;

class Tag extends Entity
{
    protected static $table = 'item_tag';
    protected static $pk = ['item_id', 'tag_id'];

    protected static $columns = [
        'item_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'tag_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => 'SNOWGIRL_SHOP\Entity\Tag']
    ];

    protected static $indexes = [
        'ix_tag' => ['tag_id']
    ];

    public function setItemId($v)
    {
        return $this->setRequiredAttr('item_id', (int)$v);
    }

    public function getItemId()
    {
        return (int)$this->getRawAttr('item_id');
    }

    public function setTagId($v)
    {
        return $this->setRequiredAttr('tag_id', (int)$v);
    }

    public function getTagId()
    {
        return (int)$this->getRawAttr('tag_id');
    }
}