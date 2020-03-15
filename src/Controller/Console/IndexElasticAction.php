<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class IndexElasticAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        (new IndexItemElasticAction)($app);
        (new IndexCatalogElasticAction)($app);

        $app->response->addToBody(implode("\r\n", [
            __CLASS__,
            'DONE'
        ]));
    }
}