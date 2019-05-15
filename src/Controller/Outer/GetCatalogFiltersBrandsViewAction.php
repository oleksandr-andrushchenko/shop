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
use SNOWGIRL_SHOP\Catalog\URI;

class GetCatalogFiltersBrandsViewAction
{
    use PrepareServicesTrait;
    use GetFiltersCountsObjectTrait;

    /**
     * @param App      $app
     * @param URI|null $uri
     * @param bool     $async
     * @param null     $brands
     *
     * @return null|\SNOWGIRL_CORE\Response|\SNOWGIRL_CORE\View|string
     */
    public function __invoke(App $app, URI $uri = null, $async = false, &$brands = null)
    {
        $this->prepareServices($app);

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