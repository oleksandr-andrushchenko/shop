<?php

namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Entity\Attribute as AttributeEntity;
use SNOWGIRL_SHOP\Entity\Category;

class Attribute extends Manager
{
    public function getCategory(AttributeEntity $attribute): ?Category
    {
        return $this->getLinked($attribute, 'category_id');
    }
}