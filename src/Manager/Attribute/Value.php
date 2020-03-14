<?php

namespace SNOWGIRL_SHOP\Manager\Attribute;

use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Entity\Attribute\Value as AttributeValueEntity;
use SNOWGIRL_SHOP\Entity\Attribute;

class Value extends Manager
{
    public function getAttribute(AttributeValueEntity $value): Attribute
    {
        return $this->getLinked($value, 'attribute_id');
    }
}