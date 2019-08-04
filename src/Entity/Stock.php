<?php

namespace SNOWGIRL_SHOP\Entity;

use SNOWGIRL_CORE\Entity;

class Stock extends Entity implements PartnerLinkHolderInterface
{
    protected static $table = 'stock';
    protected static $pk = 'stock_id';

    protected static $columns = [
        'stock_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'title' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'images' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'link' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        //@todo add vendor id column - share another link to internal catalog...
//        'vendor_id' => ['type' => self::COLUMN_INT, 'default'=>null,'entity'=>'SNOWGIRL_SHOP\Entity\Vendor'],
        'is_active' => ['type' => self::COLUMN_INT, 'default' => 0],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];

    public function setId($v)
    {
        return $this->setStockId($v);
    }

    public function getId($makeCompositeId = true)
    {
        return $this->getStockId();
    }

    public function setStockId($v)
    {
        return $this->setRequiredAttr('stock_id', (int)$v);
    }

    public function getStockId()
    {
        return (int)$this->getRawAttr('stock_id');
    }

    public function setTitle($title)
    {
        return $this->setRequiredAttr('title', $title);
    }

    public function getTitle()
    {
        return $this->getRawAttr('title');
    }

    public function setImages($images)
    {
        return $this->setRequiredAttr('images', implode(',', array_filter(array_map('trim', explode(',', $images)), function ($url) {
            return !!parse_url($url);
        })));
    }

    public function getImages($array = false)
    {
        $v = $this->getRawAttr('images');
        return $array ? explode(',', $v) : $v;
    }

    public function setLink($href)
    {
        return $this->setRequiredAttr('link', $href);
    }

    public function getLink()
    {
        return $this->getRawAttr('link');
    }

    public function getPartnerLink()
    {
        return $this->getLink();
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