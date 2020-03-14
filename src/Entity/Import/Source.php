<?php

namespace SNOWGIRL_SHOP\Entity\Import;

use DateTime;
use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Entity;

/**
 * Class Source
 *
 * @package SNOWGIRL_SHOP\Entity\Import
 * @method Source set($k, $v)
 * @method \SNOWGIRL_SHOP\Manager\Import\Source getManager()
 * @property int      import_source_id
 * @property string   name
 * @property string   file
 * @property array    file_filter
 * @property array    file_mapping
 * @property string   uri
 * @property string   tech_notes
 * @property bool     is_cron
 * @property int      vendor_id
 * @property string   class
 * @property string   source_column
 * @property DateTime created_at
 * @property DateTime updated_at
 */
class Source extends Entity
{
    public const TYPE_PARTNER = 0;
    public const TYPE_OWN = 1;

    protected static $table = 'import_source';
    protected static $pk = 'import_source_id';

    protected static $columns = [
        'import_source_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'name' => ['type' => self::COLUMN_TEXT, self::REQUIRED, self::SEARCH_IN, self::SEARCH_DISPLAY],
        'file' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'file_filter' => ['type' => self::COLUMN_TEXT, 'default' => ''],
        'file_mapping' => ['type' => self::COLUMN_TEXT, 'default' => ''],
        'uri' => ['type' => self::COLUMN_TEXT],
        'is_cron' => ['type' => self::COLUMN_INT, 'default' => 0],
        'vendor_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => 'SNOWGIRL_SHOP\Entity\Vendor'],
        'class_name' => ['type' => self::COLUMN_TEXT],
        'delivery_notes' => ['type' => self::COLUMN_TEXT],
        'sales_notes' => ['type' => self::COLUMN_TEXT],
        'tech_notes' => ['type' => self::COLUMN_TEXT],
        'type' => ['type' => self::COLUMN_INT, 'range' => [
            'Партнерские предложения' => self::TYPE_PARTNER,
            'Свои предложения' => self::TYPE_OWN
        ], 'default' => self::TYPE_PARTNER],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v): Entity
    {
        return $this->setImportSourceId($v);
    }

    public function getId(bool $makeCompositeId = true)
    {
        return $this->getImportSourceId();
    }

    public function setImportSourceId($v)
    {
        return $this->setRequiredAttr('import_source_id', (int)$v);
    }

    public function getImportSourceId()
    {
        return (int)$this->getRawAttr('import_source_id');
    }

    /**
     * @param $v
     *
     * @return Entity
     * @throws Entity\EntityException
     */
    public function setName($v)
    {
        return $this->setRequiredAttr('name', trim($v));
    }

    public function getName()
    {
        return $this->getRawAttr('name');
    }

    /**
     * @param $v
     *
     * @return Entity
     * @throws Entity\EntityException
     */
    public function setFile($v)
    {
        return $this->setRequiredAttr('file', trim($v));
    }

    public function getFile()
    {
        return $this->getRawAttr('file');
    }

    public function setFileFilter($v)
    {
        return $this->setRawAttr('file_filter', self::normalizeJson($v));
    }

    public function getFileFilter($array = false)
    {
        $v = $this->getRawAttr('file_filter');
        return $array ? self::jsonToArray($v) : $v;
    }

    public function setFileMapping($v)
    {
        return $this->setRawAttr('file_mapping', self::normalizeJson($v));
    }

    public function getFileMapping($array = false)
    {
        $v = $this->getRawAttr('file_mapping');
        return $array ? self::jsonToArray($v) : $v;
    }

    public function setUri($v)
    {
        return $this->setRawAttr('uri', trim($v));
    }

    public function getUri()
    {
        return $this->getRawAttr('uri');
    }

    public function setIsCron($v)
    {
        return $this->setRawAttr('is_cron', $v ? 1 : 0);
    }

    public function getIsCron()
    {
        return 1 == $this->getRawAttr('is_cron');
    }

    public function isCron()
    {
        return true == $this->getIsCron();
    }

    /**
     * @param $v
     *
     * @return Entity
     * @throws Entity\EntityException
     */
    public function setVendorId($v)
    {
        return $this->setRequiredAttr('vendor_id', (int)$v);
    }

    public function getVendorId()
    {
        return (int)$this->getRawAttr('vendor_id');
    }

    /**
     * @param $v
     *
     * @return Entity
     * @throws Entity\EntityException
     */
    public function setClassName($v)
    {
        return $this->setRequiredAttr('class_name', trim($v));
    }

    public function getClassName()
    {
        return $this->getRawAttr('class_name');
    }

    public function setType($v)
    {
        return $this->setRawAttr('type', self::normalizeInt($v));
    }

    public function getType()
    {
        return (int)$this->getRawAttr('type');
    }

    public function setDeliveryNotes($v)
    {
        return $this->setRawAttr('delivery_notes', trim($v));
    }

    public function getDeliveryNotes()
    {
        return $this->getRawAttr('delivery_notes');
    }

    public function setSalesNotes($v)
    {
        return $this->setRawAttr('sales_notes', trim($v));
    }

    public function getSalesNotes()
    {
        return $this->getRawAttr('sales_notes');
    }

    public function setTechNotes($v)
    {
        return $this->setRawAttr('tech_notes', trim($v));
    }

    public function getTechNotes()
    {
        return $this->getRawAttr('tech_notes');
    }

    public function setCreatedAt($v)
    {
        return $this->setRawAttr('created_at', self::normalizeTime($v));
    }

    public function getCreatedAt($datetime = false)
    {
        return $datetime ? self::timeToDatetime($this->getRawAttr('created_at')) : $this->getRawAttr('created_at');
    }

    public function setUpdatedAt($v)
    {
        return $this->setRawAttr('updated_at', self::normalizeTime($v, true));
    }

    public function getUpdatedAt($datetime = false)
    {
        return $datetime ? self::timeToDatetime($this->getRawAttr('updated_at')) : $this->getRawAttr('updated_at');
    }
}