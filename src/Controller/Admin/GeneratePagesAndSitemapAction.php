<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Controller\Admin\ExecTrait;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class GeneratePagesAndSitemapAction
{
    use PrepareServicesTrait;
    use ExecTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_GENERATE_PAGES);
        $app->rbac->checkPerm(RBAC::PERM_GENERATE_SITEMAP);

        self::_exec($app, 'Страницы и sitemap успешно обновлены', function (App $app) {
//            App::increaseMemoryLimit();
            $app->seo->getPages()->update();
            $app->seo->getSitemap()->update();
        });

        $app->request->redirectToRoute('admin', 'generate-sitemap');
    }
}