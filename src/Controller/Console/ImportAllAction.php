<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Controller\Console\FlushCacheAction;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_SHOP\Import;

class ImportAllAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app, ImportSource $importSource = null)
    {
        $this->prepareServices($app);

        $debug = 1 == $app->request->get('param_1', 1);
        $rotate = 1 == $app->request->get('param_2', 1);

        Import::factoryAndRun($app, $importSource, $debug);

        if ($rotate) {
            (new IndexElasticAction)($app);
            (new FlushCacheAction)($app);
        }
    }
}