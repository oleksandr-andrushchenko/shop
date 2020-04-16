<?php

namespace SNOWGIRL_SHOP\Import;

use SNOWGIRL_SHOP\Import;
use SNOWGIRL_SHOP\Entity\Item;

/**
 * @todo param processing (as for asos)
 * Class Admitad
 * @package SNOWGIRL_SHOP\Import
 */
class Admitad extends Import
{
    protected $sources = [
        'tag_id' => ['name', 'entity'],
        'color_id' => ['name', 'entity', 'description'],
        'material_id' => ['name', 'entity', 'description'],
//        'size_id' => ['description'],
        'season_id' => ['description']
    ];

    protected $mappings = [
        'name' => ['column' => 'name'],
        'partner_item_id' => ['column' => 'id'],
        'image' => ['column' => 'picture'],
        'price' => ['column' => 'price'],
        'old_price' => ['column' => 'oldprice'],
        'entity' => ['column' => 'typePrefix'],
        'description' => ['column' => 'description'],
        'category_id' => ['column' => 'categoryId', 'modify' => [], 0 => 'modify_only'],
        'brand_id' => ['column' => 'vendor'],
        'source_item_id' => ['column' => 'id'],
        'partner_link' => ['column' => 'url'],
        'is_in_stock' => ['column' => 'available', 'modify' => ['true' => ['value' => '1', 'tags' => []]], 0 => 'modify_only'],
        'partner_updated_at' => ['column' => 'modified_time']
    ];

    protected $langs = ['ru'];
    protected $isCheckUpdatedAt = true;

    public function getFilename(): string
    {
        if ($lastOkImport = $this->getLastOkImport()) {
            return $this->source->getFile() . '&last_import=' . $lastOkImport->getCreatedAt(true)->format('Y.m.d.H.i');
        }

        return parent::getFilename();
    }

    protected $paramsIndex;
    protected $paramsCallbacks;
    protected $paramsValues;

    protected function before()
    {
        $this->paramsIndex = isset($this->indexes['param']);

        if ($this->paramsIndex) {
            $this->paramsCallbacks = [];

            $this->paramsCallbacks['size_id'] = function ($params) {
                $output = [];

                foreach (['Размер', 'Объем', 'size', 'Size'] as $k) {
                    if (!empty($params[$k])) {
                        $output = array_merge($output, array_map('trim', explode(',', $params[$k])));
                    }
                }

                return $output ?: null;
            };

            $this->paramsCallbacks['color_id'] = function ($params) {
                $output = [];

                foreach (['Цвет', 'color', 'Color', 'ЦВЕТ', 'ОСНОВНОЙ ЦВЕТ'] as $k) {
                    if (!empty($params[$k])) {
                        $output = array_merge($output, array_map('trim', explode(',', $params[$k])));
                    }
                }

                return $output ?: null;
            };

            $this->paramsCallbacks['season_id'] = function ($params) {
                if (!empty($params['Сезонность'])) {
                    return array_map('trim', explode(',', $params['Сезонность']));
                }

                return null;
            };

            $this->paramsCallbacks['country_id'] = function ($params) {
                if (!empty($params['Страна-изготовитель'])) {
                    return array_map('trim', explode(',', $params['Страна-изготовитель']))[0];
                }

                return null;
            };

            $this->paramsCallbacks['material_id'] = function ($params) {
                $output = [];

                foreach (['Материал', 'СОСТАВ'] as $k) {
                    if (!empty($params[$k])) {
                        $output = array_merge($output, array_map('trim', explode(',', $params[$k])));
                    }
                }

                return $output ?: null;
            };

//            $this->paramsCallbacks['collection_id'] = function ($params) {
//                if (!empty($params['Коллекция'])) {
//                    return array_map('trim', explode(',', $params['Коллекция']));
//                }
//
//                return null;
//            };

        }
    }

    protected function rememberAllMvaByRow($row, $partnerItemId = null)
    {
        if ($this->paramsIndex) {
            $params = [];

            foreach (explode('|', trim($row[$this->indexes['param']])) as $tmp) {
                $tmp = explode(':', $tmp);

                if (isset($tmp[0]) && isset($tmp[1])) {
                    $k = trim($tmp[0]);
                    $v = trim($tmp[1]);

                    if (isset($params[$k])) {
                        $params[$k] .= ',' . $v;
                    } else {
                        $params[$k] = $v;
                    }
                }
            }

//            $params = Arrays::mapByKeyValueMaker(explode('|', trim($row[$this->indexes['param']])), function ($k, $v) {
//                $tmp = explode(':', $v);
//                return [trim($tmp[0]), trim($tmp[1])];
//            });

            $this->paramsValues = [];

            foreach ($this->paramsCallbacks as $pk => $callback) {
                $this->paramsValues[$pk] = $callback($params);
            }
        }

        parent::rememberAllMvaByRow($row, $partnerItemId);
    }

    protected function getParamValue($pk)
    {
        if ($this->paramsIndex && isset($this->paramsValues[$pk])) {
            return $this->paramsValues[$pk];
        }
    }

    /**
     * Returns mixed names
     *
     * @param $row
     * @return array
     */
    protected function getColorsByRow($row)
    {
        if ($pk = $this->getParamValue('color_id')) {
            return $pk;
        }

        return parent::getColorsByRow($row);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     * @return array
     */
    protected function getSeasonsByRow($row)
    {
        if ($pk = $this->getParamValue('season_id')) {
            return $pk;
        }

        return parent::getSeasonsByRow($row);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     * @return string|integer
     */
    protected function getCountryByRow($row)
    {
        if ($pk = $this->getParamValue('country_id')) {
            return $pk;
        }

        return parent::getCountryByRow($row);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     * @return array
     */
    protected function getSizesByRow($row)
    {
        if ($pk = $this->getParamValue('size_id')) {
            return $pk;
        }

        return parent::getSizesByRow($row);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     * @return array
     */
    protected function getMaterialsByRow($row)
    {
        if ($pk = $this->getParamValue('material_id')) {
            return $pk;
        }

        return parent::getMaterialsByRow($row);
    }

    public function getItemTargetLink(Item $item)
    {
        if ($parsedUrl = parse_url($item->getPartnerLink())) {
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $query);

                if ($query && isset($query['ulp'])) {
                    return $query['ulp'];
                }
            }
        }

        return parent::getItemTargetLink($item);
    }
}
