<?php

namespace SNOWGIRL_SHOP\Entity\Import;

use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Entity;

class History extends Entity
{
    protected static $table = 'import_history';
    protected static $pk = 'import_history_id';

    protected static $columns = [
        'import_history_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'import_source_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__ . '\Source'],
        'file_unique_hash' => ['type' => self::COLUMN_TEXT],
        'is_ok' => ['type' => self::COLUMN_INT, self::REQUIRED],
        //@todo add statisstics (total in file, passed, skipped etc.)
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v)
    {
        return $this->setImportHistoryId($v);
    }

    public function getId($makeCompositeId = true)
    {
        return $this->getImportHistoryId();
    }

    public function setImportHistoryId($v)
    {
        return $this->setRequiredAttr('import_history_id', (int)$v);
    }

    public function getImportHistoryId()
    {
        return (int)$this->getRawAttr('import_history_id');
    }

    /**
     * @param $v
     *
     * @return History
     * @throws Exception\EntityAttr\Required
     */
    public function setImportSourceId($v)
    {
        return $this->setRequiredAttr('import_source_id', (int)$v);
    }

    public function getImportSourceId()
    {
        return (int)$this->getRawAttr('import_source_id');
    }

    public function setFileUniqueHash($v)
    {
        return $this->setRawAttr('file_unique_hash', trim($v));
    }

    public function getFileUniqueHash()
    {
        return $this->getRawAttr('file_unique_hash');
    }

    public function setIsOk($v)
    {
        return $this->setRawAttr('is_ok', $v ? 1 : 0);
    }

    public function getIsOk()
    {
        return 1 == $this->getRawAttr('is_ok');
    }

    public function isOk()
    {
        return true == $this->getIsOk();
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