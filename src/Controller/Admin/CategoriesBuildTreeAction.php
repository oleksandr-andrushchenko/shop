<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class CategoriesBuildTreeAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_BUILD_CATEGORIES_TREE);

        try {
            if ($app->utils->categories->doBuildTreeByNames($app->request->get('delimiter', '/'), $error)) {
                $app->views->getLayout(true)->addMessage('DONE', Layout::MESSAGE_SUCCESS);
            } else {
                $app->views->getLayout(true)->addMessage('FAILED: ' . $error, Layout::MESSAGE_ERROR);
            }
        } catch (\Exception $ex) {
            $app->views->getLayout(true)->addMessage('FAILED: ' . $ex->getMessage(), Layout::MESSAGE_ERROR);
        }

        $app->request->redirect($app->request->getReferer());
    }
}