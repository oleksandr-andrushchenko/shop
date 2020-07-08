<?php

namespace SNOWGIRL_SHOP\Import\Admitad;

class LamodaUa extends LamodaRu
{
    protected $filters = [
        'categoryId' => [
            'equal' => ['женщ'],
            'not_equal' => ['муж', 'дево', 'мальч', 'детс'],
        ],
        'currencyId' => [
            'equal' => ['UAH'],
        ]
    ];
}