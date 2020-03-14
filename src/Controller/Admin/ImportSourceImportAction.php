<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class ImportSourceImportAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_RUN_IMPORT);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequestHttpException)->setInvalidParam('import_source_id');
        }

        if (!$source = $app->managers->sources->find($id)) {
            throw (new NotFoundHttpException)->setInvalidParam('import_source');
        }

        $app->managers->sources->getImport($source)->run();

        $app->request->redirectBack();
    }
}