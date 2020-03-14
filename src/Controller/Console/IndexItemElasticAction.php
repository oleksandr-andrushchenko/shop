<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Console as App;

class IndexItemElasticAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $debug = 1 == $app->request->get('param_1', 1);

        $app->response->addToBody(implode("\r\n", [
            __CLASS__,
            ($aff = $app->utils->items($debug)->doIndexElastic()) ? "DONE: {$aff}" : 'FAILED'
        ]));
    }
}