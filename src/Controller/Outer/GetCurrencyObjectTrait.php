<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/15/19
 * Time: 12:28 AM
 */

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_SHOP\App\Web as App;

trait GetCurrencyObjectTrait
{
    public function getCurrencyObject(App $app)
    {
        return (object)[
            'iso' => $iso = $app->config->catalog->currency('RUB'),
            'text' => $app->trans->makeText('catalog.currency_' . $iso)
        ];
    }
}