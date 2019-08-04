<?php

namespace SNOWGIRL_SHOP\Import;

use SNOWGIRL_SHOP\Import;
use SNOWGIRL_SHOP\Entity\Item;

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

    /**
     * Returns mixed names
     *
     * @param $row
     *
     * @return array
     */
    protected function getColorsByRow($row)
    {
        if (isset($this->indexes['param']) && $source = trim($row[$this->indexes['param']])) {
            if (preg_match('#Цвет:([a-zA-ZА-Яа-яЁё\s,-]+)#ui', $source, $tmp)) {
                return explode(',', $tmp[1]);
            }
        }

        return parent::getColorsByRow($row);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     *
     * @return array
     */
    protected function getSeasonsByRow($row)
    {
        if (isset($this->indexes['param']) && $source = trim($row[$this->indexes['param']])) {
            if (preg_match('#Сезонность:([a-zA-ZА-Яа-яЁё\s,-]+)#ui', $source, $tmp)) {
                return explode(',', $tmp[1]);
            }
        }

        return parent::getSeasonsByRow($row);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     *
     * @return string|integer
     */
    protected function getCountryByRow($row)
    {
        if (isset($this->indexes['param']) && $source = trim($row[$this->indexes['param']])) {
            if (preg_match('#Страна-изготовитель:([a-zA-ZА-Яа-яЁё\s,-]+)#ui', $source, $tmp)) {
                return explode(',', $tmp[1])[0];
            }
        }

        return parent::getCountryByRow($row);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     *
     * @return array
     */
    protected function getSizesByRow($row)
    {
        if (isset($this->indexes['param']) && $source = trim($row[$this->indexes['param']])) {
            if (preg_match('#(Размер|Объем):([a-zA-ZА-Яа-яЁё\s,0-9]+)#ui', $source, $tmp)) {
                return explode(',', $tmp[2]);
            }

            if (preg_match('#Unit=([a-zA-ZА-Яа-яЁё\s,0-9:]+)#', $source, $tmp)) {
                $tmp = explode(',', $tmp[1]);

                foreach ($tmp as $k => $v) {
                    if ('NS:0' == $v) {
                        $tmp[$k] = 'NS';
                    }
                }

                return $tmp;
            }
        }

        return parent::getSizesByRow($row);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     *
     * @return array
     */
    protected function getMaterialsByRow($row)
    {
        if (isset($this->indexes['param']) && $source = trim($row[$this->indexes['param']])) {
            if (preg_match_all('#Материал([^:]+)?:([a-zA-ZА-Яа-яЁё\s,-]+)#ui', $source, $tmp)) {
                $output = [];

                foreach ($tmp[2] as $v) {
                    $output = array_merge($output, explode(',', $v));
                }

                return $output;
            }
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
