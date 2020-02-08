<?php

namespace SNOWGIRL_SHOP\Entity\Import;

use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\DateTime;

class History extends Entity
{
    protected static $table = 'import_history';
    protected static $pk = 'import_history_id';

    protected static $columns = [
        'import_history_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'import_source_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__ . '\Source'],
        'hash' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'count_total' => ['type' => self::COLUMN_INT, 'default' => null],
        'count_filtered_filter' => ['type' => self::COLUMN_INT, 'default' => null],
        'count_filtered_modifier' => ['type' => self::COLUMN_INT, 'default' => null],
        'count_skipped_unique' => ['type' => self::COLUMN_INT, 'default' => null],
        'count_skipped_updated' => ['type' => self::COLUMN_INT, 'default' => null],
        'count_skipped_other' => ['type' => self::COLUMN_INT, 'default' => null],
        'count_passed' => ['type' => self::COLUMN_INT, 'default' => null],
        'count_affected' => ['type' => self::COLUMN_INT, 'default' => null],
        'error' => ['type' => self::COLUMN_TEXT, 'default' => null],
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

    public function setImportHistoryId($v): self
    {
        return $this->setRequiredAttr('import_history_id', (int)$v);
    }

    public function getImportHistoryId()
    {
        return (int)$this->getRawAttr('import_history_id');
    }

    public function setImportSourceId($v): self
    {
        return $this->setRequiredAttr('import_source_id', (int)$v);
    }

    public function getImportSourceId()
    {
        return (int)$this->getRawAttr('import_source_id');
    }

    public function setHash($v): self
    {
        return $this->setRequiredAttr('hash', trim($v));
    }

    public function getHash()
    {
        return $this->getRawAttr('hash');
    }

    public function setCountTotal(int $v = null): self
    {
        return $this->setRawAttr('count_total', $v);
    }

    public function getCountTotal()
    {
        return is_null($v = $this->getRawAttr('count_total')) ? null : (int)$v;
    }

    public function setCountFilteredFilter(int $v = null): self
    {
        return $this->setRawAttr('count_filtered_filter', $v);
    }

    public function getCountFilteredFilter()
    {
        return is_null($v = $this->getRawAttr('count_filtered_filter')) ? null : (int)$v;
    }

    public function setCountFilteredModifier(int $v = null): self
    {
        return $this->setRawAttr('count_filtered_modifier', $v);
    }

    public function getCountFilteredModifier()
    {
        return is_null($v = $this->getRawAttr('count_filtered_modifier')) ? null : (int)$v;
    }

    public function setCountSkippedUnique(int $v = null): self
    {
        return $this->setRawAttr('count_skipped_unique', $v);
    }

    public function getCountSkippedUnique()
    {
        return is_null($v = $this->getRawAttr('count_skipped_unique')) ? null : (int)$v;
    }

    public function setCountSkippedUpdated(int $v = null): self
    {
        return $this->setRawAttr('count_skipped_updated', $v);
    }

    public function getCountSkippedUpdated()
    {
        return is_null($v = $this->getRawAttr('count_skipped_updated')) ? null : (int)$v;
    }

    public function setCountSkippedOther(int $v = null): self
    {
        return $this->setRawAttr('count_skipped_other', $v);
    }

    public function getCountSkippedOther()
    {
        return is_null($v = $this->getRawAttr('count_skipped_other')) ? null : (int)$v;
    }

    public function setCountPassed(int $v = null): self
    {
        return $this->setRawAttr('count_passed', $v);
    }

    public function getCountPassed()
    {
        return is_null($v = $this->getRawAttr('count_passed')) ? null : (int)$v;
    }

    public function setCountAffected(int $v = null): self
    {
        return $this->setRawAttr('count_affected', $v);
    }

    public function getCountAffected()
    {
        return is_null($v = $this->getRawAttr('count_affected')) ? null : (int)$v;
    }

    public function setError($v): self
    {
        return $this->setAttr('error', ($v = trim($v)) ? $v : null);
    }

    public function getError()
    {
        return ($v = $this->getRawAttr('error')) ? $v : null;
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

    public function getWhen(): ?string
    {
        if (!$createdAt = $this->getCreatedAt(true)) {
            return null;
        }

        if (!$diff = (new DateTime)->diff($createdAt)) {
            return null;
        }

        return $diff->format('%a:%H:%I:%S');
    }

    public function getDuration(): ?string
    {
        if (!$createdAt = $this->getCreatedAt(true)) {
            return null;
        }

        if (!$updatedAt = $this->getUpdatedAt(true)) {
            return null;
        }

        if (!$diff = $updatedAt->diff($createdAt)) {
            return null;
        }

        return $diff->format('%a:%H:%I:%S');
    }
}