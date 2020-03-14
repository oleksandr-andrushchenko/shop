<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_SHOP\Http\HttpApp as App;
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
            throw (new BadRequestHttpException)->setInvalidParam('id');
        }

        if (!$column = $app->request->get('column')) {
            throw (new BadRequestHttpException)->setInvalidParam('column');
        }

        $entity = $app->managers->getByEntityPk($column)->getEntity()->getClass();

        $app->response->setJSON(200, $app->utils->attrs->getIdToName($entity));
    }
}