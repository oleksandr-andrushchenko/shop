<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_SHOP\Http\HttpApp as App;

class VendorsAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->request->redirect($app->router->makeLink('default', ['action' => 'shops']), 301);
    }
}