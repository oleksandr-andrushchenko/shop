<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Entity\User;
use SNOWGIRL_CORE\Exception\HTTP\Forbidden;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Brand;
use SNOWGIRL_SHOP\RBAC;

class FixBrandsUpperCaseAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_ALL);

        /** @var Brand $item */
        foreach ($app->managers->brands->clear()->getObjects() as $item) {
            $tmp = ucwords($item->getName());

            if ($tmp != $item->getName()) {
                $item->setName($tmp);
                $app->managers->brands->updateOne($item);
            }
        }

        $app->request->redirectToRoute('admin');
    }
}