<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class FixItemsDuplicatesAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$importSourceId = $app->request->get('param_1')) {
            throw (new BadRequestHttpException)->setInvalidParam('import_source_id');
        }

        $app->response->setBody(implode("\r\n", [
            __CLASS__,
            $app->utils->items->doFixDuplicates($importSourceId) ? 'DONE' : 'FAILED'
        ]));
    }
}