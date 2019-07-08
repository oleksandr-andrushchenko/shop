<?php

namespace SNOWGIRL_SHOP\Entity;

use SNOWGIRL_SHOP\Entity\Item\Attr;

/**
 * Class Vendor
 *
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
//        'sales_notes' => ['type' => self::COLUMN_TEXT],
        'is_active' => ['type' => self::COLUMN_INT, self::REQUIRED, 'default' => 0],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v)
    {
        return $this->setVendorId($v);
    }

    public function getId($makeCompositeId = true)
    {
        return $this->getVendorId();
    }

    public function setVendorId($v)
    {
        return $this->setRequiredAttr('vendor_id', (int)$v);
    }

    public function getVendorId()
    {
        return (int)$this->getRawAttr('vendor_id');
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

    public function setIsActive($v)
    {
        return $this->setRawAttr('is_active', $v ? 1 : 0);
    }

    public function getIsActive()
    {
        return (int)$this->getRawAttr('is_active');
    }

    public function isActive()
    {
        return 1 == $this->getIsActive();
    }

    public function setCreatedAt($v)
    {
        return $this->setRawAttr('created_at', self::normalizeTime($v));
    }

    public function getCreatedAt($datetime = false)
    {
        $v = $this->getRawAttr('created_at');
        return $datetime ? self::timeToDatetime($v) : $v;
    }

    public function setUpdatedAt($v)
    {
        return $this->setRawAttr('updated_at', self::normalizeTime($v, true));
    }

    public function getUpdatedAt($datetime = false)
    {
        $v = $this->getRawAttr('updated_at');
        return $datetime ? self::timeToDatetime($v) : $v;
    }
}
