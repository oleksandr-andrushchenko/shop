<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 9:52 PM
 */

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\Forbidden;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalog;

class TogglePageCatalogCustomSeoTextActiveAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     *
     * @throws Forbidden
     * @throws NotFound
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$app->managers->catalogCustom->isCanActiveSeoTexts($app->request->getClient()->getUser())) {
            throw new Forbidden;
        }

        if (!$id = $app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        /** @var PageCatalog $pageCatalog */

        if (!$pageCatalog = $app->managers->catalog->find($id)) {
            throw new NotFound;
        }

        if (!$pageCatalogCustom = $app->managers->catalog->getPageCatalogCustom($pageCatalog)) {
            throw new NotFound;
        }

        if (!$num = $app->request->has('num')) {
            throw (new BadRequest)->setInvalidParam('num');
        }

        $num = $app->request->get('num');
        $texts = $pageCatalogCustom->getSeoTexts(true);

        if (!isset($texts[$num])) {
            throw new NotFound;
        }

        $texts[$num]['active'] = $v = $texts[$num]['active'] ? false : true;
        $pageCatalogCustom->setSeoTexts($texts);

        $app->managers->catalogCustom->updateOne($pageCatalogCustom);

        $app->response->setJSON(200, [
            'active' => $v
        ]);
    }
}