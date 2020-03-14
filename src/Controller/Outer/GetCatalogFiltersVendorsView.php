<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_SHOP\Catalog\URI;

class GetCatalogFiltersVendorsView
{
    use GetFiltersCountsObjectTrait;

    public function __invoke(App $app, URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($app->request->getParams());
        }

        if ($async) {
            //@todo...
            $vendors = [];
        } else {
            $vendors = $app->managers->vendors->clear()->setLimit($this->getFiltersCountsObject($app)['vendor'])->getObjectsByUri($uri);
        }

        if ($vendors) {
            $view = $app->views->get('@shop/catalog/filters/vendors.phtml', [
                'uriParams' => $uri->getParams(),
                'vendors' => $vendors
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