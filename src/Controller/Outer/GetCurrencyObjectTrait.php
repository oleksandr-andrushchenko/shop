<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_SHOP\Http\HttpApp as App;

trait GetCurrencyObjectTrait
{
    public function getCurrencyObject(App $app)
    {
        return (object)[
            'iso' => $iso = $app->config('catalog.currency', 'RUB'),
            'text' => $app->trans->makeText('catalog.currency_' . $iso)
        ];
    }
}