<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_SHOP\Catalog\URI;

class GetCatalogFiltersBrandsView
{
    use GetFiltersCountsObjectTrait;

    public function __invoke(App $app, URI $uri = null, $async = false, &$brands = null)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($app->request->getParams());
        }

        if ($async) {
            //@todo...
            $brands = [];
        } else {
            $brands = $app->managers->brands->clear()->setLimit($this->getFiltersCountsObject($app)['brand'])->getObjectsByUri($uri);
        }

        if ($brands) {
            $view = $app->views->get('@shop/catalog/filters/brands.phtml', [
                'uriParams' => $uri->getParams(),
                'brands' => $brands
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