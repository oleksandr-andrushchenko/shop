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

    protected $langs = ['ru'];
    protected $filters = [];
    protected $mappings = [];

    protected $csvProcessorV2 = false;
    protected $mvaProcessorV2 = true;

    protected $csvFileDelimiter = ';';

    /**
     * Returns mixed names
     *
     * @param $row
     *
     * @return array
     */
    protected function importRowToColors($row)
    {
        if (isset($this->indexes['param']) && $source = trim($row[$this->indexes['param']])) {
            if (preg_match('#Цвет:([a-zA-ZА-Яа-яЁё\s,-]+)#ui', $source, $tmp)) {
                return explode(',', $tmp[1]);
            }
        }

        return parent::importRowToColors($row);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     *
     * @return array
     */
    protected function importRowToSeasons($row)
    {
        if (isset($this->indexes['param']) && $source = trim($row[$this->indexes['param']])) {
            if (preg_match('#Сезонность:([a-zA-ZА-Яа-яЁё\s,-]+)#ui', $source, $tmp)) {
                return explode(',', $tmp[1]);
            }
        }

        return parent::importRowToSeasons($row);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     *
     * @return string|integer
     */
    protected function importRowToCountry($row)
    {
        if (isset($this->indexes['param']) && $source = trim($row[$this->indexes['param']])) {
            if (preg_match('#Страна-изготовитель:([a-zA-ZА-Яа-яЁё\s,-]+)#ui', $source, $tmp)) {
                return explode(',', $tmp[1])[0];
            }
        }

        return parent::importRowToCountry($row);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     *
     * @return array
     */
    protected function importRowToSizes($row)
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

        return parent::importRowToSizes($row);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     *
     * @return array
     */
    protected function importRowToMaterials($row)
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

        return parent::importRowToMaterials($row);
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
