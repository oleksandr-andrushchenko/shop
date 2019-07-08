<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_SHOP\Catalog\URI;

class GetCatalogFiltersSeasonsView
{
    use GetFiltersCountsObjectTrait;


    public function __invoke(App $app, URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($app->request->getParams());
        }

        if ($async) {
            //@todo...
            $seasons = [];
        } else {
            $seasons = $app->managers->seasons->clear()->setLimit($this->getFiltersCountsObject($app)['season'])->getObjectsByUri($uri);
        }

        if ($seasons) {
            $view = $app->views->get('@shop/catalog/filters/seasons.phtml', [
                'uriParams' => $uri->getParams(),
                'seasons' => $seasons
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