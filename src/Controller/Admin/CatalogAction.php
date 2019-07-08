<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Page\Catalog\Custom as PageCatalogCustom;
use SNOWGIRL_SHOP\RBAC;

class CatalogAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_CATALOG_PAGE);

        $view = $app->views->getLayout(true);

        $content = $view->setContentByTemplate('@shop/admin/catalog.phtml', [
            'searchTerm' => $app->request->get('search_term'),
            'maxArticleLength' => $app->config->catalog->seo_text_body_length(2500),
            'client' => $app->request->getClient()->getUser(),
            'searchPrefix' => 1 == $app->request->get('search_prefix'),
            'searchInRdbms' => 1 == $app->request->get('search_in_rdbms'),
        ]);

        $page = (int)$app->request->get('page', 1);
        $size = (int)$app->request->get('size', 10);

        $manager = $app->managers->catalog->clear()
            ->setOffset(($page - 1) * $size)
            ->setLimit($size)
            ->calcTotal(true);

//        if ($content->searchTerm) {
//            $objects = $manager->getObjectsByQuery($content->searchTerm);
//        } else {
//            $objects = $manager->getObjects();
//        }
//
//        $total = $manager->getTotal();

        $params = [
            $content->searchTerm ?: '',
            $content->searchPrefix ? true : false,
            $content->searchInRdbms ? 'mysql' : null
        ];

        $objects = $manager->getObjectsByQuery(...$params);
        $total = $manager->getCountByQuery(...$params);

        $manager->addLinkedObjects($objects, ['params_hash' => PageCatalogCustom::class]);

        $content->addParams([
            'manager' => $manager,
            'managerCustom' => $app->managers->catalogCustom,
            'pages' => $objects,
            'pager' => $app->views->pager([
                'link' => $app->router->makeLink('admin', [
                    'action' => 'catalog',
                    'priority' => isset($priorities) ? $priorities : null,
                    'search' => $content->searchTerm,
                    'page' => '{page}'
                ]),
                'total' => $total,
                'size' => $size,
                'page' => $page,
                'per_set' => 5,
                'param' => 'page'
            ], $view)
        ]);

        $app->response->setHTML(200, $view);
    }
}