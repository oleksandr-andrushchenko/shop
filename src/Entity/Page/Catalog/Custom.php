<?php

namespace SNOWGIRL_SHOP\Entity\Page\Catalog;

use SNOWGIRL_CORE\Entity;

class Custom extends Entity
{
    protected static $table = 'page_catalog_custom';
    protected static $pk = 'page_catalog_custom_id';
    protected static $columns = [
        'page_catalog_custom_id' => ['type' => self::COLUMN_INT, self::AUTO_INCREMENT],
        'params_hash' => ['type' => self::COLUMN_TEXT, self::MD5, self::REQUIRED, self::SEARCH_IN, self::SEARCH_DISPLAY],
        'meta_title' => ['type' => self::COLUMN_TEXT],
        'meta_description' => ['type' => self::COLUMN_TEXT],
        'meta_keywords' => ['type' => self::COLUMN_TEXT],
        'h1' => ['type' => self::COLUMN_TEXT],
        'body' => ['type' => self::COLUMN_TEXT],
        'seo_texts' => ['type' => self::COLUMN_TEXT, self::JSON],
        'created_at' => ['type' => self::COLUMN_TIME, self::REQUIRED],
        'updated_at' => ['type' => self::COLUMN_TIME, 'default' => null]
    ];
    protected static $indexes = [
        'uk_params' => ['params_hash']
    ];

    public function setId($v): Entity
    {
        return $this->setPageCatalogCustomId($v);
    }

    public function getId(bool $makeCompositeId = true)
    {
        return $this->getPageCatalogCustomId();
    }

    /**
     * @param $v
     *
     * @return Entity
     * @throws Entity\EntityException
     */
    public function setPageCatalogCustomId($v)
    {
        return $this->setRequiredAttr('page_catalog_custom_id', (int)$v);
    }

    public function getPageCatalogCustomId()
    {
        return (int)$this->getRawAttr('page_catalog_custom_id');
    }

    /**
     * @param $v
     *
     * @return Entity
     */
    public function setParamsHash($v)
    {
        return $this->setRequiredAttr('params_hash', self::normalizeHash($v));
    }

    public function getParamsHash()
    {
        return $this->getRawAttr('params_hash');
    }

    /**
     * @param $v
     *
     * @return Custom
     */
    public function setMetaTitle($v)
    {
        return $this->setRawAttr('meta_title', self::normalizeText($v, true));
    }

    public function getMetaTitle()
    {
        return $this->getRawAttr('meta_title');
    }

    /**
     * @param $v
     *
     * @return Custom
     */
    public function setMetaDescription($v)
    {
        return $this->setRawAttr('meta_description', self::normalizeText($v, true));
    }

    public function getMetaDescription()
    {
        return $this->getRawAttr('meta_description');
    }

    /**
     * @param $v
     *
     * @return Custom
     */
    public function setMetaKeywords($v)
    {
        return $this->setRawAttr('meta_keywords', self::normalizeText($v, true));
    }

    public function getMetaKeywords()
    {
        return $this->getRawAttr('meta_keywords');
    }

    /**
     * @param $v
     *
     * @return Custom
     */
    public function setH1($v)
    {
        return $this->setRawAttr('h1', self::normalizeText($v, true));
    }

    public function getH1()
    {
        return $this->getRawAttr('h1');
    }

    /**
     * @param $v
     *
     * @return Custom
     */
    public function setBody($v)
    {
        return $this->setRawAttr('body', trim($v));
    }

    public function getBody()
    {
        return $this->getRawAttr('body');
    }

    /**
     * @param $v
     *
     * @return Custom
     */
    public function setSeoTexts($v)
    {
        return $this->setRawAttr('seo_texts', self::normalizeJson($v, true));
    }

    public function getSeoTexts($array = false)
    {
        $v = $this->getRawAttr('seo_texts');
        return $array ? self::jsonToArray($v) : $v;
    }

    /**
     * @param array $seoText
     *
     * @return $this
     */
    public function addSeoText(array $seoText)
    {
        $seoTexts = $this->getSeoTexts(true);
        $seoTexts[] = array_filter($seoText, function ($k) {
            return in_array($k, ['h1', 'body', 'user', 'active']);
        }, ARRAY_FILTER_USE_KEY);
        $this->setSeoTexts($seoTexts);
        return $this;
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