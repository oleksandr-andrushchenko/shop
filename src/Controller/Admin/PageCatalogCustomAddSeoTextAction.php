<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalog;
use SNOWGIRL_SHOP\RBAC;

class PageCatalogCustomAddSeoTextAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_ADD_UPDATE_CATALOG_SEO_TEXT);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        $manager = $app->managers->catalog;

        /** @var PageCatalog $pageCatalog */

        if (!$pageCatalog = $manager->find($id)) {
            throw (new NotFound)->setNonExisting('page_catalog');
        }

        $pageCatalogCustom = $manager->getPageCatalogCustom($pageCatalog);

        $clientId = $app->request->getClient()->getUser()->getId();

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

        $texts[$num]['h1'] = $app->request->get('h1');
        $texts[$num]['body'] = $app->request->get('body');

        if ($active = $app->request->get('active')) {
            $app->rbac->checkPerm(RBAC::PERM_ACTIVATE_OWN_CATALOG_SEO_TEXT);

            $texts[$num]['active'] = $active;
        }

        $pageCatalogCustom->setSeoTexts($texts);

        $app->managers->catalogCustom->save($pageCatalogCustom);

        $app->request->redirect($app->request->getReferer());
    }
}