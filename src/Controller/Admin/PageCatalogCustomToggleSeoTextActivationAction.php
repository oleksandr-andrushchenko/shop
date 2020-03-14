<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalog;
use SNOWGIRL_SHOP\RBAC;

class PageCatalogCustomToggleSeoTextActivationAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequestHttpException)->setInvalidParam('id');
        }

        /** @var PageCatalog $pageCatalog */

        if (!$pageCatalog = $app->managers->catalog->find($id)) {
            throw (new NotFoundHttpException)->setNonExisting('page_catalog');
        }

        if (!$pageCatalogCustom = $app->managers->catalog->getPageCatalogCustom($pageCatalog)) {
            throw (new NotFoundHttpException)->setNonExisting('page_catalog_custom');
        }

        if (!$num = $app->request->has('num')) {
            throw (new BadRequestHttpException)->setInvalidParam('num');
        }

        $num = $app->request->get('num');
        $texts = $pageCatalogCustom->getSeoTexts(true);

        if (!isset($texts[$num])) {
            throw (new NotFoundHttpException)->setNonExisting('num');
        }

        $clientId = $app->request->getClient()->getUser()->getId();

        if ($texts[$num]['user'] == $clientId) {
            $app->rbac->checkPerm(RBAC::PERM_ACTIVATE_OWN_CATALOG_SEO_TEXT);
        } else {
            $app->rbac->checkPerm(RBAC::PERM_ACTIVATE_FOREIGN_CATALOG_SEO_TEXT);
        }

        $texts[$num]['active'] = $v = $texts[$num]['active'] ? false : true;
        $pageCatalogCustom->setSeoTexts($texts);

        $app->managers->catalogCustom->updateOne($pageCatalogCustom);

        $app->response->setJSON(200, ['active' => $v]);
    }
}