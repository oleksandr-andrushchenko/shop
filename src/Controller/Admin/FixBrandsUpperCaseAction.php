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
use SNOWGIRL_SHOP\Entity\Brand;

class FixBrandsUpperCaseAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     *
     * @throws Forbidden
     * @throws \SNOWGIRL_CORE\Exception\EntityAttr\Required
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN)) {
            throw new Forbidden;
        }

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