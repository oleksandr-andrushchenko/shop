<?php

namespace SNOWGIRL_SHOP\Entity\Item\Attribute;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_SHOP\Entity\Attribute;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\Attribute\Value as AttributeValue;


/**
 * Class Value
 *
 * @method \SNOWGIRL_SHOP\Manager\Item\Attribute\Value getManager()
 * @package SNOWGIRL_SHOP\Entity\Item\Attribute
 */
class Value extends Entity
{
    protected static $table = 'item_attribute_value';
    protected static $pk = [
        'item_id',
        'attribute_id',
        'attribute_value_id',
    ];
    protected static $columns = [
        'item_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => Item::class],
        'attribute_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => Attribute::class],
        'attribute_value_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => AttributeValue::class],
    ];

    public function setId($v): Entity
    {
        return $this->setAttributeValueId($v);
    }

    public function getId(bool $makeCompositeId = true)
    {
        return $makeCompositeId ? self::makeCompositePkIdFromPkIdArray($this->getAttributeValueId()) : $this->getAttributeValueId();
    }

    public function setAttributeValueId(array $id): Value
    {
        $this->setRequiredAttr('item_id', $id['item_id']);
        $this->setRequiredAttr('attribute_id', $id['attribute_id']);
        $this->setRequiredAttr('attribute_value_id', $id['attribute_value_id']);

        return $this;
    }

    public function getAttributeValueId(): array
    {
        return [
            'item_id' => (int)$this->getRawAttr('item_id'),
            'attribute_id' => (int)$this->getRawAttr('attribute_id'),
            'attribute_value_id' => (int)$this->getRawAttr('attribute_value_id'),
        ];
    }

    public function setName($v): Value
    {
        return $this->setRequiredAttr('name', trim($v));
    }

    public function getName(): string
    {
        return $this->getRawAttr('name');
    }
}