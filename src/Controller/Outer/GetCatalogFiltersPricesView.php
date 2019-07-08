<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_SHOP\Catalog\URI;

class GetCatalogFiltersPricesView
{
    use GetCurrencyObjectTrait;

    public function __invoke(App $app, URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($app->request->getParams());
        }

        if ($async) {
            //@todo...
            $prices = [];
        } else {
            $prices = $app->managers->items->clear()->getPricesByUri($uri);
        }

        if ($prices) {
            $view = $app->views->get('@shop/catalog/filters/prices.phtml', [
                'uriParams' => $uri->getParams(),
                'priceFrom' => $uri->get(URI::PRICE_FROM),
                'priceTo' => $uri->get(URI::PRICE_TO),
                'currency' => $this->getCurrencyObject($app),
                'prices' => $prices
            ]);

            $view = (string)$view;
            $view = trim($view);
        } else {
            $view = null;
        }

        if ($ajax) {
            return $app->response->setJSON(200, [
                'view' => $view
            ]);
        }

        return $view;
    }
}