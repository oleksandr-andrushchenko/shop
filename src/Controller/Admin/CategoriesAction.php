<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class CategoriesAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_CATEGORIES_PAGE);

        $view = $app->views->getLayout(true);
        $view->setContentByTemplate('@shop/admin/categories.phtml', [
            'tree' => $app->managers->categories->makeTreeHtml()
        ]);

        $app->response->setHTML(200, $view);
    }
}