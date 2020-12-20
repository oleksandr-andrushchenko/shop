<?php

namespace SNOWGIRL_SHOP\Entity;

use SNOWGIRL_SHOP\Entity\Item\Attr;
use SNOWGIRL_CORE\Entity;

/**
 * Class Vendor
 * @package SNOWGIRL_SHOP\Entity
 * @method Vendor setName($name)
 * @method Vendor setUri($name)
 */
class Vendor extends Attr implements PartnerLinkHolderInterface
{
    protected static $table = 'vendor';
    protected static $pk = 'vendor_id';

    protected static $columns = [
        'vendor_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'name' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::SEARCH_DISPLAY, self::REQUIRED],
        'partner_link' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'uri' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'image' => ['type' => self::COLUMN_TEXT, self::IMAGE],
        'class_name' => ['type' => self::COLUMN_TEXT],
        'is_in_stock_check' => ['type' => self::COLUMN_INT, self::REQUIRED, self::BOOL, 'default' => 0],
        'target_vendor_id' => ['type' => self::COLUMN_INT, 'default' => null, 'entity' => __CLASS__],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null],
    ];

    public function setId($v): Entity
    {
        return $this->setVendorId($v);
    }

    public function getId(bool $makeCompositeId = true)
    {
        return $this->getVendorId();
    }

    public function setVendorId($v)
    {
        return $this->setRequiredAttr('vendor_id', (int) $v);
    }

    public function getVendorId()
    {
        return (int) $this->getRawAttr('vendor_id');
    }

    public function setPartnerLink($v)
    {
        return $this->setRawAttr('partner_link', $v);
    }

    public function getPartnerLink()
    {
        return $this->getRawAttr('partner_link');
    }

    public function setImage($image)
    {
        return $this->setRequiredAttr('image', $image ?: null);
    }

    /**
     * @return string|null
     */
    public function getImage()
    {
        return ($v = $this->getRawAttr('image')) ? $v : null;
    }

    public function setClassName($v)
    {
        return $this->setRawAttr('class_name', trim($v));
    }

    public function getClassName()
    {
        return $this->getRawAttr('class_name');
    }

    public function setIsInStockCheck($v)
    {
        return $this->setRawAttr('is_in_stock_check', $v ? 1 : 0);
    }

    public function getIsInStockCheck(): int
    {
        return (int) $this->getRawAttr('is_in_stock_check');
    }

    public function isInStockCheck(): bool
    {
        return 1 == $this->getIsInStockCheck();
    }

    public function setTargetVendorId($v)
    {
        return $this->setRawAttr('target_vendor_id', ($v = (int) $v) ? $v : null);
    }

    public function getTargetVendorId(): ?int
    {
        return ($v = (int) $this->getRawAttr('target_vendor_id')) ? $v : null;
    }

    public function isFake(): bool
    {
        return 0 < $this->getTargetVendorId();
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
