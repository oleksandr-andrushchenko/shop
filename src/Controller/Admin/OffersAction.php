<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Entity\User;
use SNOWGIRL_CORE\Exception\HTTP\Forbidden;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
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
            'vendors' => $app->managers->vendors->clear()->getObjects(),
            'vendorClasses' => $app->managers->vendors->getAdapterClasses(true),
            'importClasses' => $app->managers->sources->getImportClasses(true),
            'sourceTypes' => ImportSource::getColumns()['type']['range'],
            'importSources' => $app->managers->sources->clear()->getObjects()
        ]);

        $content->addParams($app->request->getParams());

        $app->response->setHTML(200, $view);
    }
}