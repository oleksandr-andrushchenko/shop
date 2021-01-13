<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class IndexElasticsearchAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     *
     * @throws \SNOWGIRL_CORE\Http\Exception\NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        (new IndexItemElasticsearchAction)($app);
        (new IndexCatalogElasticsearchAction)($app);

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            'DONE',
        ]));
    }
}