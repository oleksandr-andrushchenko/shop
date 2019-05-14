<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 10:08 PM
 */

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\ExecTrait;
use SNOWGIRL_CORE\Entity\User;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\Forbidden;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalog;

class PageCatalogCustomSeoTextAction
{
    use PrepareServicesTrait;
    use ExecTrait;

    /**
     * @param App $app
     *
     * @throws Forbidden
     * @throws NotFound
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_COPYWRITER)) {
            throw new Forbidden;
        }

        if (!$id = $app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

//        dump($app->request->getParams());

        $manager = $app->managers->catalog;

        /** @var PageCatalog $pageCatalog */

        if (!$pageCatalog = $manager->find($id)) {
            throw new NotFound;
        }

        $pageCatalogCustom = $manager->getPageCatalogCustom($pageCatalog);

        $clientId = $app->request->getClient()->getUser()->getId();

        if ($app->request->has('num')) {
            if (!$pageCatalogCustom) {
                throw new NotFound;
            }

            $texts = $pageCatalogCustom->getSeoTexts(true);
            $num = $app->request->get('num');

            if (!isset($texts[$num])) {
                throw new NotFound;
            }

            if (!$app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN) && $texts[$num]['user'] != $clientId) {
                throw new Forbidden;
            }
        } else {
            if ($app->request->isDelete()) {
                throw (new BadRequest)->setInvalidParam('num');
            }

            if ($pageCatalogCustom) {
                $texts = $pageCatalogCustom->getSeoTexts(true);
                $num = count($texts);
            } else {
                $pageCatalogCustom = $manager->makeCustom($pageCatalog);
                $texts = [];
                $num = 0;
            }

            $texts[$num] = [
                'h1' => '',
                'body' => '',
                'user' => $clientId,
                'active' => 0
            ];
        }

        self::_exec($app, null, function (App $app) use ($manager, $pageCatalog, $pageCatalogCustom, $texts, $num, $clientId) {
            if ($app->request->isDelete()) {
                unset($texts[$num]);
                $texts = array_values($texts);
            } else {
                if ($isNew = (null === $num)) {
                    $num = 0;
                }

                $texts[$num]['h1'] = $app->request->get('h1');
                $texts[$num]['body'] = $app->request->get('body');

                if ($app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN)) {
                    $texts[$num]['active'] = $app->request->get('active');
                }
            }

            $pageCatalogCustom->setSeoTexts($texts);

            $app->managers->catalogCustom->save($pageCatalogCustom);

            $app->request->redirect($app->request->getReferer());
        });
    }
}