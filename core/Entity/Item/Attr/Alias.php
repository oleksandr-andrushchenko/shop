<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 4/13/18
 * Time: 10:49 AM
 */

namespace SNOWGIRL_SHOP\Entity\Item\Attr;

use SNOWGIRL_CORE\Entity;

/**
 * Class Alias
 * @package SNOWGIRL_SHOP\Entity\Item\Attr
 */
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