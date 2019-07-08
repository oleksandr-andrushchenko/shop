<?php

namespace SNOWGIRL_SHOP\Entity\Item\Attr;

use SNOWGIRL_CORE\Entity;

abstract class Alias extends Entity
{
    public static function getAttrPk()
    {
        return str_replace('_alias', '', static::getPk());
    }

    public static function getAttrTable()
    {
        return str_replace('_alias', '', static::getTable());
    }

    public static function getAttrClass()
    {
        return str_replace('\\Alias', '', static::getClass());
    }

    abstract public function getName();

    abstract public function getTitle();
}