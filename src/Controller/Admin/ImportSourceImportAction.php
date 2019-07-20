<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
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
            throw (new BadRequest)->setInvalidParam('id');
        }

        $source = $app->managers->sources->find($id);

        $app->managers->sources->getImport($source)->run(
            $app->request->get('import-offset', 0),
            $app->request->get('import-length', 999999)
        );

        $app->request->redirectBack();
    }
}