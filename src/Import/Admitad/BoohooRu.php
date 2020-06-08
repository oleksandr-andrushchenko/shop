<?php

namespace SNOWGIRL_SHOP\Import\Admitad;

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
//        '' => '',
//        '' => '',
    ];

    protected function before()
    {
        parent::before();

        if ($this->paramsIndex) {
            $this->paramsCallbacks['size_id'] = function ($params) {
                if (isset($params['size'])) {
                    return array_map('trim', preg_split('/[,\/]/', $params['size'], -1, PREG_SPLIT_NO_EMPTY));
                }
            };

            $this->paramsCallbacks['color_id'] = function ($params) {
                if (isset($params['color'])) {
                    $output = [];

                    $tmp = array_map('trim', explode(',', $params['color']));

                    foreach ($tmp as $v) {
                        if (isset($this->colorEnToRu[$v])) {
                            $output[] = $this->colorEnToRu[$v];
                        }
                    }

                    return $output ?: null;
                }
            };
        }
    }
}