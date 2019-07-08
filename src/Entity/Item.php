<?php

namespace SNOWGIRL_SHOP\Entity;

use SNOWGIRL_CORE\Entity;

/**
 * Class Item
 *
 * @package SNOWGIRL_SHOP\Entity
 * @method \SNOWGIRL_SHOP\Manager\Item getManager()
 */
class Item extends Entity
{
    protected static $table = 'item';
    protected static $pk = 'item_id';
    protected static $isFtdbmsIndex = true;
    protected static $columns = [
        'item_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'name' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::SEARCH_DISPLAY, self::REQUIRED, self::FTDBMS_FIELD],
        'upc' => ['type' => self::COLUMN_TEXT, self::SEARCH_IN, self::FTDBMS_FIELD, self::REQUIRED],
        'image' => ['type' => self::COLUMN_TEXT, self::IMAGE, self::REQUIRED],
        'image_count' => ['type' => self::COLUMN_INT, 'default' => null],
        'price' => ['type' => self::COLUMN_FLOAT, self::REQUIRED],
        'old_price' => ['type' => self::COLUMN_FLOAT, 'default' => null],
        'entity' => ['type' => self::COLUMN_TEXT],
        'description' => ['type' => self::COLUMN_TEXT],
        'rating' => ['type' => self::COLUMN_INT, 'default' => 0],

        'category_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'default' => null, 'entity' => __NAMESPACE__ . '\Category'],
        'brand_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__ . '\Brand'],
        'country_id' => ['type' => self::COLUMN_INT, 'default' => null, 'entity' => __NAMESPACE__ . '\Country'],
        'vendor_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__ . '\Vendor'],

//        'tag_id' => ['type' => self::COLUMN_ARRAY, 'default' => null, 'entity' => __NAMESPACE__ . '\Tag'],
//        'color_id' => ['type' => self::COLUMN_ARRAY, 'default' => null, 'entity' => __NAMESPACE__ . '\Color'],
//        'material_id' => ['type' => self::COLUMN_ARRAY, 'default' => null, 'entity' => __NAMESPACE__ . '\Material'],
//        'size_id' => ['type' => self::COLUMN_ARRAY, 'default' => null, 'entity' => __NAMESPACE__ . '\Size'],
//        'season_id' => ['type' => self::COLUMN_ARRAY, 'default' => null, 'entity' => __NAMESPACE__ . '\Season'],

        'is_sport' => ['type' => self::COLUMN_INT, 'default' => 0],
        'is_size_plus' => ['type' => self::COLUMN_INT, 'default' => 0],
        'partner_link' => ['type' => self::COLUMN_TEXT, self::REQUIRED],
        'is_in_stock' => ['type' => self::COLUMN_INT, 'default' => 0],
        'import_source_id' => ['type' => self::COLUMN_INT, self::REQUIRED, 'entity' => __NAMESPACE__ . '\Import\Source'],

        'order_desc_rating' => ['type' => self::COLUMN_INT, 'default' => 0],
        'order_asc_price' => ['type' => self::COLUMN_INT, 'default' => 0],
        'order_desc_price' => ['type' => self::COLUMN_INT, 'default' => 0],

        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];
    protected static $indexes = [
        'ix_catalog_category_brand' => ['is_sport', 'is_size_plus', 'category_id', 'brand_id'],
        'uk_vendor_upc' => ['vendor_id', 'upc'],
        'ix_fix_category_vendor' => ['category_id', 'vendor_id', 'created_at', 'updated_at'],
        'ix_fix_vendor' => ['vendor_id', 'created_at', 'updated_at'],
        'ix_order_desc_rating' => ['order_desc_rating'],
        'ix_order_asc_price' => ['order_asc_price'],
        'ix_order_desc_price' => ['order_desc_price']
    ];

    /**
     * @param $v
     *
     * @return Entity
     * @throws \SNOWGIRL_CORE\Exception\EntityAttr\Required
     */
    public function setId($v)
    {
        return $this->setItemId($v);
    }

    public function getId($makeCompositeId = true)
    {
        return $this->getItemId();
    }

    /**
     * @param $v
     *
     * @return Entity
     * @throws \SNOWGIRL_CORE\Exception\EntityAttr\Required
     */
    public function setItemId($v)
    {
        return $this->setRequiredAttr('item_id', (int)$v);
    }

    public function getItemId()
    {
        return (int)$this->getRawAttr('item_id');
    }

    /**
     * @param $v
     *
     * @return Entity
     * @throws \SNOWGIRL_CORE\Exception\EntityAttr\Required
     */
    public function setName($v)
    {
        return $this->setRequiredAttr('name', trim($v));
    }

    public function getName()
    {
        return $this->getRawAttr('name');
    }

    public function setPartnerLink($v)
    {
        return $this->setRawAttr('partner_link', $v);
    }

    public function getPartnerLink()
    {
        return $this->getRawAttr('partner_link');
    }

    /**
     * @param $image
     *
     * @return Entity
     * @throws \SNOWGIRL_CORE\Exception\EntityAttr\Required
     */
    public function setImage($image)
    {
        return $this->setRequiredAttr('image', $image);
    }

    public function getImage()
    {
        return $this->getRawAttr('image');
    }

    public function setImageCount($v)
    {
        return $this->setRawAttr('image_count', ($v = (int)$v) ? $v : null);
    }

    public function getImageCount()
    {
        return (int)$this->getRawAttr('image_count');
    }

    public function setPrice($v)
    {
        return $this->setRawAttr('price', (float)$v);
    }

    public function getPrice($format = false)
    {
        $output = (float)$this->getRawAttr('price');

        if ($format) {
            $output = sprintf('%01.2f', $output);
        }

        return $output;
    }

    public function setOldPrice($v)
    {
        return $this->setRawAttr('old_price', ($v = (float)$v) ? $v : null);
    }

    public function getOldPrice($format = false)
    {
        if ($output = $this->getRawAttr('old_price')) {
            $output = (float)$output;

            if ($format) {
                $output = sprintf('%01.2f', $output);
            }

            return $output;
        }

        return null;
    }

    public function isSales()
    {
        return $this->getOldPrice() > 0;
    }

    public function getEntity()
    {
        if ($v = $this->getRawAttr('entity')) {
            return $v;
        }

        return null;
    }

    public function setDescription($v)
    {
        return $this->setRawAttr('description', $v ? trim($v) : null);
    }

    public function getDescription()
    {
        return $this->getRawAttr('description');
    }

    public function setRating($v)
    {
        return $this->setRawAttr('rating', (int)$v);
    }

    public function getRating()
    {
        return (int)$this->getRawAttr('rating');
    }

    public function setCategoryId($v)
    {
        return $this->setRawAttr('category_id', $v ? (int)$v : null);
    }

    public function getCategoryId()
    {
        return ($v = $this->getRawAttr('category_id')) ? (int)$v : null;
    }

    public function setBrandId($v)
    {
        return $this->setRawAttr('brand_id', $v ? (int)$v : null);
    }

    public function getBrandId()
    {
        return ($v = $this->getRawAttr('brand_id')) ? (int)$v : null;
    }

    public function setCountryId($v)
    {
        return $this->setRawAttr('country_id', $v ? (int)$v : null);
    }

    public function getCountryId()
    {
        return ($v = $this->getRawAttr('country_id')) ? (int)$v : null;
    }

    public function setVendorId($v)
    {
        return $this->setRawAttr('vendor_id', $v ? (int)$v : null);
    }

    public function getVendorId()
    {
        return ($v = $this->getRawAttr('vendor_id')) ? (int)$v : null;
    }

    public function setIsSport($v)
    {
        return $this->setRawAttr('is_sport', $v ? 1 : 0);
    }

    public function getIsSport()
    {
        return (int)$this->getRawAttr('is_sport');
    }

    public function isSport()
    {
        return 1 == $this->getIsSport();
    }

    public function setIsSizePlus($v)
    {
        return $this->setRawAttr('is_size_plus', $v ? 1 : 0);
    }

    public function getIsSizePlus()
    {
        return (int)$this->getRawAttr('is_size_plus');
    }

    public function isSizePlus()
    {
        return 1 == $this->getIsSizePlus();
    }

    /**
     * @param $v
     *
     * @return Entity
     * @throws \SNOWGIRL_CORE\Exception\EntityAttr\Required
     */
    public function setUpc($v)
    {
        return $this->setRequiredAttr('upc', $v);
    }

    public function getUpc()
    {
        return $this->getRawAttr('upc');
    }

    public function setIsInStock($v)
    {
        return $this->setRawAttr('is_in_stock', $v ? 1 : 0);
    }

    public function getIsInStock()
    {
        return (int)$this->getRawAttr('is_in_stock');
    }

    public function isInStock()
    {
        return 1 == $this->getIsInStock();
    }

    public function getPercentageDiscount()
    {
        if (!$this->getOldPrice()) {
            return null;
        }

        if ($this->getOldPrice() <= $this->getPrice()) {
            return null;
        }

        if ($v = $this->getOldPrice() - $this->getPrice()) {
            if ($v = ceil(100 * ($v / $this->getOldPrice()))) {
                return $v;
            }
        }

        return null;
    }

    public function isNewly()
    {
        if (!$v = $this->getCreatedAt()) {
            return false;
        }

        if (!is_numeric($v)) {
            $v = \DateTime::createFromFormat('Y-m-d H:i:s', $v)->getTimestamp();
        }

        return (time() - (int)$v) < 24 * 3600;
    }

    /**
     * @param $v
     *
     * @return Entity
     * @throws \SNOWGIRL_CORE\Exception\EntityAttr\Required
     */
    public function setImportSourceId($v)
    {
        return $this->setRequiredAttr('import_source_id', self::normalizeInt($v));
    }

    public function getImportSourceId()
    {
        return (int)$this->getRawAttr('import_source_id');
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