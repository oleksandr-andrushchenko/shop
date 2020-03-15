<?php

namespace SNOWGIRL_SHOP\Entity\Material;

use SNOWGIRL_CORE\Entity;

class Term extends \SNOWGIRL_SHOP\Entity\Term
{
    protected static $table = 'material_term';
    protected static $pk = 'id';

    protected static $columns = [
        'id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'material_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'lang' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'value' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v): Entity
    {
        return $this->setRequiredAttr('id', (int)$v);
    }

    public function getId(bool $makeCompositeId = true)
    {
        return (int)$this->getRawAttr('id');
    }

    public function setMaterialId($v)
    {
        return $this->setRequiredAttr('material_id', (int)$v);
    }

    public function getMaterialId()
    {
        return (int)$this->getRawAttr('material_id');
    }

    public function setLang($v)
    {
        return $this->setRequiredAttr('lang', trim($v));
    }

    public function getLang()
    {
        return $this->getRawAttr('lang');
    }

    public function setValue($v)
    {
        return $this->setRequiredAttr('value', trim($v));
    }

    public function getValue()
    {
        return $this->getRawAttr('value');
    }

    public function getComponentId()
    {
        return $this->getMaterialId();
    }
}