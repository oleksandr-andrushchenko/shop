<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class IndexCatalogElasticsearchAction
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

        $debug = 1 == $app->request->get('param_1', 1);

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            ($aff = $app->utils->catalog($debug)->doIndexElastic()) ? "DONE: {$aff}" : 'FAILED',
        ]));
    }
}