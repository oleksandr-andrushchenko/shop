<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_SHOP\Http\HttpApp as App;

class BuyAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        (new GoAction)($app, 'item', $app->request->get('id'), $app->request->get('source'));
    }
}