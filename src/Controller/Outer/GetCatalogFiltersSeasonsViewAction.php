<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_SHOP\Catalog\URI;

class GetCatalogFiltersSeasonsViewAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app, URI $uri = null, $async = false)
    {
        $this->prepareServices($app);

        (new GetCatalogFiltersSeasonsView)(...func_get_args());
    }
}