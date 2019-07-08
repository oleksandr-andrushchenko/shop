<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Console as App;

class IndexElasticAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        (new IndexItemElasticAction)($app);
        (new IndexCatalogElasticAction)($app);

        $app->response->setBody(implode("\r\n", [
            __CLASS__,
            'DONE'
        ]));
    }
}