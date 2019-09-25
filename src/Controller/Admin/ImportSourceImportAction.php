<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_SHOP\App\Web as App;
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
            throw (new BadRequest)->setInvalidParam('import_source_id');
        }

        if (!$source = $app->managers->sources->find($id)) {
            throw (new NotFound)->setInvalidParam('import_source');
        }

        $app->managers->sources->getImport($source)->run();

        $app->request->redirectBack();
    }
}