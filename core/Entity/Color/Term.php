<?php
/**
 * Created by JetBrains PhpStorm.
 * User: snowgirl
 * Date: 13.01.16
 * Time: 10:52
 * To change this template use File | Settings | File Templates.
 */
namespace SNOWGIRL_SHOP\Entity\Color;

use SNOWGIRL_CORE\Entity;

/**
 * Class Term
 * @package SNOWGIRL_SHOP\Entity\Color
 */
class Term extends \SNOWGIRL_SHOP\Entity\Term
{
    protected static $table = 'color_term';
    protected static $pk = 'id';

    protected static $columns = [
        'id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'color_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__],
        'lang' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'value' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v)
    {
        return $this->setRequiredAttr('id', (int)$v);
    }

    public function getId($makeCompositeId = true)
    {
        return (int)$this->getRawAttr('id');
    }

    public function setColorId($v)
    {
        return $this->setRequiredAttr('color_id', (int)$v);
    }

    public function getColorId()
    {
        return (int)$this->getRawAttr('color_id');
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
        return $this->getColorId();
    }
}