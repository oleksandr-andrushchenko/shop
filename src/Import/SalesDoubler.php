<?php

namespace SNOWGIRL_SHOP\Import;

use SNOWGIRL_SHOP\Import;

class SalesDoubler extends Import
{
    protected $xml;
    protected $xpath;
    protected $categories;
    protected $categoriesParents;

    protected function getXML()
    {
        if (null === $this->xml) {
            $this->xml = simplexml_load_file($this->getDownloadedRawFileName());
        }

        return $this->xml;
    }

    protected function initXPath()
    {
        $this->xpath = array();
        $this->xpath['offer'] = '/yml_catalog/shop/offers/offer';
        $this->xpath['currency'] = '/yml_catalog/shop/currencies/currency';
        $this->xpath['category'] = '/yml_catalog/shop/categories/category';

        return true;
    }

    protected function initCategories()
    {
        $this->categories = array();
        $this->categoriesParents = array();

        foreach ($this->getXML()->xpath($this->xpath['category']) as $category) {
            /** @var \SimpleXMLElement $category */
            /** @var \stdClass $attr */
            $attr = $category->attributes();

            $id = (string)$attr->id;
            $parentId = (string)$attr->parentId;
            $name = (string)$category;

            $this->categories[$id] = $name;
            $this->categoriesParents[$id] = $parentId;
        }

        $tmp = array();

        foreach (array_keys($this->categories) as $id) {
            $tmp[$id] = $this->buildCategoryName($id);
        }

        $this->categories = $tmp;
        return true;
    }

    protected function buildCategoryName($id, $tmp = array())
    {
        if (!isset($this->categoriesParents[$id])) {
            return implode('|', $tmp);
        }

        $tmp[] = $this->categories[$id];

        return $this->buildCategoryName($this->categoriesParents[$id], $tmp);
    }

    protected function makeCsv()
    {
        if (!$this->getXML()) {
            return false;
        }

        $this->initXPath();
        $this->initCategories();

        $xml = $this->getXML()->xpath($this->xpath['offer']);

        $csv = fopen($this->getDownloadedTmpCsvFileName(), 'w');

        fwrite($csv, implode(';', array_keys($this->getRow($xml[0]))) . "\r\n");

        for ($i = 0, $s = count($xml); $i < $s; $i++) {
            fwrite($csv, implode(';', array_values($this->getRow($xml[$i]))) . "\r\n");
        }

        fclose($csv);

        rename($this->getDownloadedTmpCsvFileName(), $this->getDownloadedRawFileName());

        return true;
    }

    protected function getRow(\SimpleXMLElement $offer)
    {
        $tmp = array();

        foreach ($offer->attributes() as $k => $v) {
            $tmp[$k] = (string)$v;
        }

        foreach ($offer->children() as $k => $v) {
            /** @var \SimpleXMLElement $v */
            if ('pictures' == $k) {
                $tmp[$k] = array();

                foreach ($v->children() as $vv) {
                    /** @var \SimpleXMLElement $vv */
                    $tmp[$k][] = $vv->attributes()->url;
                }
            } elseif ('picture' == $k) {
                if (!isset($tmp[$k])) {
                    $tmp[$k] = array();
                }

                $tmp[$k][] = (string)$v;
            } elseif ('param' == $k) {
                $tmp[(string)$v->attributes()->name] = (string)$v;
            } else {
                $tmp[$k] = (string)$v;
            }
        }

        foreach (array('picture', 'pictures') as $k) {
            if (isset($tmp[$k])) {
                $tmp[$k] = implode(',', $tmp[$k]);
            }
        }

        $tmp['categoryId'] = $this->categories[$tmp['categoryId']];

        return $tmp;
    }

    protected static function _getOfferRow(\SimpleXMLElement $xml)
    {
        /** @var \SimpleXMLElement $v */
        $tmp = array();

        foreach ($xml->attributes() as $k => $v)
            $tmp[$k] = (string)$v;

        foreach ($xml->children() as $k => $v)
            if ('pictures' == $k) {
                $tmp[$k] = array();

                foreach ($v->children() as $vv) {
                    $tmp[$k][] = $vv->attributes()->url;
                }
            } elseif ('picture' == $k) {
                if (!isset($tmp[$k])) {
                    $tmp[$k] = array();
                }

                $tmp[$k][] = (string)$v;
            } elseif ('param' == $k) {
                $tmp[(string)$v->attributes()->name] = (string)$v;
            } else {
                $tmp[$k] = (string)$v;
            }

        foreach (array('picture', 'pictures') as $k) {
            if (isset($tmp[$k])) {
                $tmp[$k] = implode(',', $tmp[$k]);
            }
        }

        return $tmp;
    }
}