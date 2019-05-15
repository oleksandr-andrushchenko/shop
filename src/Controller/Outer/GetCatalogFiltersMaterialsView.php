<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/15/19
 * Time: 12:18 AM
 */

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_SHOP\Catalog\URI;

class GetCatalogFiltersMaterialsView
{
    use GetFiltersCountsObjectTrait;

    /**
     * @param App      $app
     * @param URI|null $uri
     * @param bool     $async
     *
     * @return null|\SNOWGIRL_CORE\Response|\SNOWGIRL_CORE\View|string
     */
    public function __invoke(App $app, URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($app->request->getParams());
        }

        if ($async) {
            //@todo...
            $materials = [];
        } else {
            $materials = $app->managers->materials->clear()->setLimit($this->getFiltersCountsObject($app)['material'])->getObjectsByUri($uri);
        }

        if ($materials) {
            $view = $app->views->get('@shop/catalog/filters/materials.phtml', [
                'uriParams' => $uri->getParams(),
                'materials' => $materials
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