<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_SHOP\Catalog\URI;

class GetCatalogFiltersTagsViewAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app, URI $uri = null, $async = false)
    {
        $this->prepareServices($app);

        (new GetCatalogFiltersTagsView)(...func_get_args());
    }
}