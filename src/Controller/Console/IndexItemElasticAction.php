<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class IndexItemElasticAction
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

        $app->container->updateDefinition('indexer', ['enabled' => true]);

        $debug = 1 == $app->request->get('param_1', 1);

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            ($aff = $app->utils->items($debug)->doIndexElastic()) ? "DONE: {$aff}" : 'FAILED',
        ]));
    }
}