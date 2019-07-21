<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class ImportSourceGetMapToPossibleValuesAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_ALL);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        if (!$column = $app->request->get('column')) {
            throw (new BadRequest)->setInvalidParam('column');
        }

        $entity = $app->managers->getByEntityPk($column)->getEntity()->getClass();

        $app->response->setJSON(200, $app->utils->attrs->getIdToName($entity));
    }
}