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

    public function __invoke(App $app, URI $uri = null, $async = false)
    {
        $this->prepareServices($app);

        (new GetCatalogFiltersBrandsView)(...func_get_args());
    }
}