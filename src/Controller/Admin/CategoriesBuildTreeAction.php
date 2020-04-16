<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;
use Throwable;

class CategoriesBuildTreeAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws \SNOWGIRL_CORE\Exception
     * @throws \SNOWGIRL_CORE\Http\Exception\ForbiddenHttpException
     */
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
        } catch (Throwable $e) {
            $app->views->getLayout(true)->addMessage('FAILED: ' . $e->getMessage(), Layout::MESSAGE_ERROR);
        }

        $app->request->redirect($app->request->getReferer());
    }
}