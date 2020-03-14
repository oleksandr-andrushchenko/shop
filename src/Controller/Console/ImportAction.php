<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\App\Console as App;

class ImportAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$importSourceId = $app->request->get('param_1')) {
            throw (new BadRequestHttpException)->setInvalidParam('import_source_id');
        }

        if (!$importSource = $app->managers->sources->find($importSourceId)) {
            throw (new NotFoundHttpException)->setNonExisting('import_source');
        }

        if (!$importSource->isCron()) {
            throw new Exception('not in cron');
        }

        $app->request->set('param_1', $app->request->get('param_2', 0));
        $app->request->set('param_2', $app->request->get('param_3', 0));

        (new ImportAllAction)($app, $importSource);
    }
}