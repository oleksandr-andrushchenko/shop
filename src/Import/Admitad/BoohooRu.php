<?php

namespace SNOWGIRL_SHOP\Import\Admitad;

use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\Import\Admitad;

class BoohooRu extends Admitad
{
    protected $sources = [
        'tag_id' => ['name'],
        'color_id' => ['param'],
        'material_id' => ['name', 'description'],
        'season_id' => ['description']
    ];

    protected $filters = [
//        'param' => [
//            'equal' => ['Female']
//        ],
//        'currencyId' => [
//            'equal' => ['RUB']
//        ]
    ];

    protected $langs = ['ru', 'en'];

    protected $paramsIndex;
    protected $params;

    protected function before()
    {
        $this->paramsIndex = isset($this->indexes['param']);
    }

    protected function importAllMvaByRow($row, $partnerItemId = null)
    {
        if ($this->paramsIndex) {
            $this->params = Arrays::mapByKeyValueMaker(explode('|', $row[$this->indexes['param']]), function ($k, $v) {
                $tmp = explode(':', $v);
                return [trim($tmp[0]), trim($tmp[1])];
            });
        }

        parent::importAllMvaByRow($row, $partnerItemId);
    }

    protected function getSizesByRow($row)
    {
        if ($this->paramsIndex) {
            if (isset($this->params['size'])) {
                return array_map('trim', preg_split('/[,\/]/', $this->params['size'], -1, PREG_SPLIT_NO_EMPTY));
            }
        }

        return parent::getSizesByRow($row);
    }

    protected $colorEnToRu = [
        'coral' => 'кораловый',
        'lilac' => 'сиреневый',
        'olive' => 'оливковый',
        'black' => 'черный',
        'stone' => 'камень',
        'ecru' => 'бежевый',
        'green' => 'зеленый',
        'white' => 'белый',
        'purple' => 'фиолетовый',
        'biscuit' => 'бисквитный',
        'washed lime' => 'салатовый',
        'grey' => 'серый',
        'washed coral' => '',
        'pink' => '',
        'blue' => '',
        'grey marl' => '',
        'toffee' => 'ириска',
        'silver' => '',
        'salmon' => 'лососевый',
        'caramel' => '',
        'sand' => '',
        'khaki' => '',
        '' => '',
        '' => '',
    ];

    protected function getColorsByRow($row)
    {
        if ($this->paramsIndex) {
            if (isset($this->params['color'])) {
                return array_map('trim', explode(',', $this->params['color']));
            }
        }

        return parent::getColorsByRow($row);
    }
}