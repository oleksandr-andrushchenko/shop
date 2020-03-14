<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class GeneratePagesAndSitemapAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_GENERATE_PAGES);
        $app->rbac->checkPerm(RBAC::PERM_GENERATE_SITEMAP);

//        App::increaseMemoryLimit();
        $app->seo->getPages()->update();
        $app->seo->getSitemap()->update();

        $app->request->redirectBack();
    }
}