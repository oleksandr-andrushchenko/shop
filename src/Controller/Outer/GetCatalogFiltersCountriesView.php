<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_SHOP\Catalog\URI;

class GetCatalogFiltersCountriesView
{
    use GetFiltersCountsObjectTrait;

    public function __invoke(App $app, URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($app->request->getParams());
        }

        if ($async) {
            //@todo...
            $countries = [];
        } else {
            $countries = $app->managers->countries->clear()->setLimit($this->getFiltersCountsObject($app)['country'])->getObjectsByUri($uri);
        }

        if ($countries) {
            $view = $app->views->get('@shop/catalog/filters/countries.phtml', [
                'uriParams' => $uri->getParams(),
                'countries' => $countries
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