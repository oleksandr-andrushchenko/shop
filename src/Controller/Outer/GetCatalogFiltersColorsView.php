<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_SHOP\Catalog\URI;

class GetCatalogFiltersColorsView
{
    use GetFiltersCountsObjectTrait;

    public function __invoke(App $app, URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($app->request->getParams());
        }

        if ($async) {
            //@todo...
            $colors = [];
        } else {
            $colors = $app->managers->colors->clear()->setLimit($this->getFiltersCountsObject($app)['color'])->getObjectsByUri($uri, false);
        }

        if ($colors) {
            $view = $app->views->get('@shop/catalog/filters/colors.phtml', [
                'uriParams' => $uri->getParams(),
                'colors' => $colors
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