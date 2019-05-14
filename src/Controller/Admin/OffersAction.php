<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 9:52 PM
 */

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Entity\User;
use SNOWGIRL_CORE\Exception\HTTP\Forbidden;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;

class OffersAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     *
     * @throws Forbidden
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        $view = $app->views->getLayout(true);

        $content = $view->setContentByTemplate('@snowgirl-shop/admin/offers.phtml', [
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