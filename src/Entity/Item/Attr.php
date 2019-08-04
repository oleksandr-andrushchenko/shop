<?php

namespace SNOWGIRL_SHOP\Entity\Item;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_SHOP\Catalog\URI;

/**
 * Class Attr
 *
 * @property string uri
 * @property string name
 * @package SNOWGIRL_SHOP\Entity\Item
 */
abstract class Attr extends Entity
{
    public static function getLinkPk()
    {
        return 'item_' . static::getPk();
    }

    public static function getLinkTable()
    {
        return 'item_' . static::getTable();
    }

    public static function normalizeUri($v, $null = false)
    {
        $tmp = parent::normalizeUri($v);

        if (is_numeric(substr($tmp, strlen($tmp) - 2))) {
            $tmp = $tmp . '-' . static::getTable();
        }

        return $tmp;
    }

    public function setUri($v)
    {
        return $this->setRequiredAttr('uri', $this->normalizeUri($v));
    }

    public function getUri()
    {
        return $this->getRawAttr('uri');
    }

    /**
     * @param $v
     *
     * @return Entity|Attr
     * @throws \SNOWGIRL_CORE\Exception\EntityAttr\Required
     */
    public function setName($v)
    {
        return $this->setRequiredAttr('name', $this->normalizeText($v));
    }

    public function getName()
    {
        return $this->getRawAttr('name');
    }

    /**
     * @todo optimize...
     * @return URI
     */
    public function getCatalogUri()
    {
        return new URI([$this->getPk() => $this->getId()]);
    }
}