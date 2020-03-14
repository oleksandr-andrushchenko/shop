<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class GenerateCategoryEntitiesAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_ALL);

        $app->managers->categoriesToEntities->generate();

        $app->request->redirectBack();
    }
}