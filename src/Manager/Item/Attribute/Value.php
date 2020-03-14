<?php

namespace SNOWGIRL_SHOP\Manager\Item\Attribute;

use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Entity\Attribute;
use SNOWGIRL_SHOP\Entity\Item\Attribute\Value as ItemAttributeValue;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\Attribute\Value as AttributeValue;

class Value extends Manager
{
    public function getItem(ItemAttributeValue $value): Item
    {
        return $this->getLink($value, 'item_id');
    }

    public function getAttribute(ItemAttributeValue $value): Attribute
    {
        return $this->getLinked($value, 'attribute_id');
    }

    public function getAttributeValue(ItemAttributeValue $value): AttributeValue
    {
        return $this->getLinked($value, 'attribute_value_id');
    }
}