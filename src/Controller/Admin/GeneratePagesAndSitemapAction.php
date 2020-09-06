<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use Exception;
use SNOWGIRL_CORE\Http\Exception\ForbiddenHttpException;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class GeneratePagesAndSitemapAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws Exception
     * @throws ForbiddenHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_GENERATE_PAGES);
        $app->rbac->checkPerm(RBAC::PERM_GENERATE_SITEMAP);

//        App::increaseMemoryLimit();
        $app->utils->catalog->doGenerate();
//        $app->utils->catalog->doIndexIndexer();
        $app->utils->sitemap->doGenerate();

        $app->request->redirectBack();
    }
}