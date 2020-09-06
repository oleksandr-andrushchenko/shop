<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Controller\Console\FlushCacheAction;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_SHOP\Import;

class ImportAllAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @param ImportSource|null $importSource
     * @throws NotFoundHttpException
     */
    public function __invoke(App $app, ImportSource $importSource = null)
    {
        $this->prepareServices($app);

        $debug = 1 == $app->request->get('param_1', 0);
        $profile = 1 == $app->request->get('param_2', 0);
        $rotate = 1 == $app->request->get('param_3', 0);

        Import::factoryAndRun($app, $importSource, $debug, $profile);

        if ($rotate) {
            (new IndexElasticAction)($app);
            (new FlushCacheAction)($app);
        }
    }
}