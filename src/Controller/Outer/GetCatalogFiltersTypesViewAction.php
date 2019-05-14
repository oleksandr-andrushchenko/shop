<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/15/19
 * Time: 12:18 AM
 */

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_SHOP\Catalog\SEO;
use SNOWGIRL_SHOP\Catalog\URI;

class GetCatalogFiltersTypesViewAction
{
    use PrepareServicesTrait;

    /**
     * @param App      $app
     * @param URI|null $uri
     * @param bool     $async
     *
     * @return null|\SNOWGIRL_CORE\Response|\SNOWGIRL_CORE\View|string
     */
    public function __invoke(App $app, URI $uri = null, $async = false)
    {
        $this->prepareServices($app);

        if ($ajax = null === $uri) {
            $uri = new URI($app->request->getParams());
        }

        if ($async) {
            //@todo...
            $types = [];
        } else {
            $types = array_keys($app->managers->items->clear()->getTypesByUri($uri));
        }

        if ($types) {
            $view = $app->views->get('@snowgirl-shop/catalog/filters/types.phtml', [
                'uriParams' => $uri->getParams(),
                'typesNames' => SEO::getTypesToTexts(true),
                'types' => $types
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