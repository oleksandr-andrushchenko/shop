<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 9:52 PM
 */

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_CORE\Entity\User;
use SNOWGIRL_SHOP\Entity\Page\Catalog\Custom as PageCatalogCustom;
use SNOWGIRL_CORE\Exception\HTTP\Forbidden;

class CatalogAction
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

        if (!$app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_COPYWRITER)) {
            throw new Forbidden;
        }

        $view = $app->views->getLayout(true);

        $content = $view->setContentByTemplate('@shop/admin/catalog.phtml', [
            'search' => $app->request->get('search'),
            'maxArticleLength' => $app->config->catalog->seo_text_body_length(2500),
            'client' => $app->request->getClient()->getUser(),
        ]);

        $page = (int)$app->request->get('page', 1);
        $size = (int)$app->request->get('size', 10);

        $manager = $app->managers->catalog->clear()
            ->setOffset(($page - 1) * $size)
            ->setLimit($size)
            ->calcTotal(true);

        if ($content->search) {
            $objects = $manager->getObjectsByQuery($content->search);
        } else {
            $objects = $manager->getObjects();
        }

        $total = $manager->getTotal();
        $manager->addLinkedObjects($objects, ['params_hash' => PageCatalogCustom::class]);

        $content->addParams([
            'manager' => $manager,
            'managerCustom' => $app->managers->catalogCustom,
            'pages' => $objects,
            'pager' => $app->views->pager([
                'link' => $app->router->makeLink('admin', [
                    'action' => 'catalog',
                    'priority' => isset($priorities) ? $priorities : null,
                    'search' => $content->search,
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