<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class DropImportSourceCacheAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$id = $app->request->get('param_1')) {
            throw (new BadRequestHttpException)->setInvalidParam('id');
        }

        if (!$source = $app->managers->sources->find($id)) {
            throw (new NotFoundHttpException)->setNonExisting('source');
        }

        $aff = $app->managers->sources->getImport($source)->dropCache(true);

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            'AFF: ' . var_export($aff, true),
        ]));
    }
}