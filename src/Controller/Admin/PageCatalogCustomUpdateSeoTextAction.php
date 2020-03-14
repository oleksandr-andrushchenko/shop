<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalog;
use SNOWGIRL_SHOP\RBAC;

class PageCatalogCustomUpdateSeoTextAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequestHttpException)->setInvalidParam('id');
        }

        if (!$app->request->has('num')) {
            throw (new BadRequestHttpException)->setInvalidParam('num');
        }

        $manager = $app->managers->catalog;

        /** @var PageCatalog $pageCatalog */

        if (!$pageCatalog = $manager->find($id)) {
            throw (new NotFoundHttpException)->setNonExisting('page_catalog');
        }

        if (!$pageCatalogCustom = $manager->getPageCatalogCustom($pageCatalog)) {
            throw (new NotFoundHttpException)->setNonExisting('page_catalog_custom');
        }

        $texts = $pageCatalogCustom->getSeoTexts(true);
        $num = $app->request->get('num');

        if (!isset($texts[$num])) {
            throw (new NotFoundHttpException)->setNonExisting('num');
        }

        $clientId = $app->request->getClient()->getUser()->getId();

        if ($texts[$num]['user'] == $clientId) {
            $app->rbac->checkPerm(RBAC::PERM_ADD_UPDATE_CATALOG_SEO_TEXT);
        } else {
            $app->rbac->checkPerm(RBAC::PERM_UPDATE_FOREIGN_CATALOG_SEO_TEXT);
        }

        $texts[$num]['h1'] = $app->request->get('h1');
        $texts[$num]['body'] = $app->request->get('body');

        if ($active = $app->request->get('active')) {
            if ($texts[$num]['user'] == $clientId) {
                $app->rbac->checkPerm(RBAC::PERM_ACTIVATE_OWN_CATALOG_SEO_TEXT);
            } else {
                $app->rbac->checkPerm(RBAC::PERM_ACTIVATE_FOREIGN_CATALOG_SEO_TEXT);
            }

            $texts[$num]['active'] = $active;
        }

        $pageCatalogCustom->setSeoTexts($texts);

        $app->managers->catalogCustom->save($pageCatalogCustom);

        $app->request->redirectBack();
    }
}