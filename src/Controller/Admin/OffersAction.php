<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class OffersAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_OFFERS_PAGE);

        $view = $app->views->getLayout(true);

        $content = $view->setContentByTemplate('@shop/admin/offers.phtml', [
            'vendors' => $app->managers->vendors->clear()->addOrder(['target_vendor_id' => SORT_ASC])->getObjects(),
            'vendorClasses' => $app->managers->vendors->getAdapterClasses(true),
            'importClasses' => $app->managers->sources->getImportClasses(true),
            'importSources' => $app->managers->sources->clear()->addOrder(['is_cron' => SORT_DESC])->getObjects()
        ]);

        $content->addParams($app->request->getParams());

        $app->response->setHTML(200, $view);
    }
}