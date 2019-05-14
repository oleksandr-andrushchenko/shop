<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 10:50 PM
 */

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Controller\Console\RotateMcmsAction;
use SNOWGIRL_SHOP\App\Console as App;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_SHOP\Import;

class ImportAllAction
{
    use PrepareServicesTrait;

    /**
     * @param App               $app
     * @param ImportSource|null $importSource
     *
     * @throws \SNOWGIRL_CORE\Exception\HTTP\NotFound
     */
    public function __invoke(App $app, ImportSource $importSource = null)
    {
        $this->prepareServices($app);

//        $app->configMaster = null;

        $rotate = $app->request->get('param_1', true);

//        $app->services->mcms->disable();

        Import::factoryAndRun($app, $importSource);

        if ($rotate) {
            (new IndexElasticAction)($app);
            (new RotateMcmsAction)($app);
        }
    }
}