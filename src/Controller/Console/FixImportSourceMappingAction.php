<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class FixImportSourceMappingAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$oldColumnName = $app->request->get('param_1')) {
            throw (new BadRequestHttpException)->setInvalidParam('old_column_name');
        }

        if (!$newColumnName = $app->request->get('param_2')) {
            throw (new BadRequestHttpException)->setInvalidParam('new_column_name');
        }

        $aff = 0;

        foreach ($app->managers->sources->getObjects() as $source) {
            $mapping = $source->getFileMapping(true);

            foreach ($mapping as $from => $to) {
                if ($oldColumnName == $from) {
                    $mapping[$newColumnName] = $to;
                    unset($mapping[$from]);
                }
            }

            $source->setFileMapping($mapping);

            if ($app->managers->sources->updateOne($source)) {
                $aff += 1;
            }
        }

        $app->response->setBody("DONE: {$aff}");
    }
}