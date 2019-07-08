<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_SHOP\Catalog\URI;

class GetCatalogFiltersSizesView
{
    use GetFiltersCountsObjectTrait;

    public function __invoke(App $app, URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($app->request->getParams());
        }

        if ($async) {
            //@todo...
            $sizes = [];
        } else {
            $sizes = $app->managers->sizes->clear()->setLimit($this->getFiltersCountsObject($app)['size'])->getObjectsByUri($uri);
        }

        if ($sizes) {
            $view = $app->views->get('@shop/catalog/filters/sizes.phtml', [
                'uriParams' => $uri->getParams(),
                'sizes' => $sizes
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