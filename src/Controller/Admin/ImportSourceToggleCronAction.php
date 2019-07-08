<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Entity\User;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\Forbidden;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class ImportSourceToggleCronAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_TOGGLE_IMPORT_SOURCE_CRON);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        if (!$source = $app->managers->sources->find($id)) {
            throw new NotFound;
        }

        $source->setIsCron($source->isCron() ? 0 : 1);
        $app->managers->sources->updateOne($source);

        $app->response->setJSON(200, [
            'is_cron' => $source->getIsCron()
        ]);
    }
}