<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_SHOP\Catalog\URI;

class GetCatalogFiltersTagsView
{
    use GetFiltersCountsObjectTrait;

    public function __invoke(App $app, URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($app->request->getParams());
        }

        if ($async) {
            //@todo...
            $tags = [];
        } else {
            $tags = $app->managers->tags->clear()->setLimit($this->getFiltersCountsObject($app)['tag'])->getObjectsByUri($uri);
        }

        if ($tags) {
            $view = $app->views->get('@shop/catalog/filters/tags.phtml', [
                'uriParams' => $uri->getParams(),
                'tags' => $tags
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