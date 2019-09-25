<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Controller\Console\RotateMcmsAction;
use SNOWGIRL_SHOP\App\Console as App;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_SHOP\Import;

class ImportAllAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app, ImportSource $importSource = null)
    {
        $this->prepareServices($app);

        $force = 1 == $app->request->get('param_1', 0);
        $rotate = 1 == $app->request->get('param_2', 1);

        Import::factoryAndRun($app, $importSource, $force);

        if ($rotate) {
            (new IndexElasticAction)($app);
            (new RotateMcmsAction)($app);
        }
    }
}