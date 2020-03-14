<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Ads;
use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\View\Widget\Grid\Items as ItemsGrid;
use SNOWGIRL_CORE\View\Widget\Ad\LargeRectangle as LargeRectangleAd;

class GetCatalogItemsAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app, $type = null, $id = null, $source = null)
    {
        $this->prepareServices($app);

        $uri = new URI($app->request->getParams());
        $src = $uri->getSRC();

        $referer = $app->request->getReferer();
        $path = $app->request->getPathInfoByUri($referer);

        if ($app->router->getRoute('item')->match($path)) {
            $bannerKey = 'item-catalog-grid';
        } else {
            $bannerKey = 'catalog-grid';
        }

        if ((!$app->request->getDevice()->isMobile()) && $gridBanner = $app->ads->findBanner(LargeRectangleAd::class, $bannerKey, [Ads::GOOGLE, Ads::YANDEX])) {
            $src->setLimit($src->getLimit() - ItemsGrid::getBannerCellsCount());
        }

        $app->response->setJSON(200, [
            'view' => $app->views->items([
                'items' => $src->getItems($total),
                'offset' => $src->getOffset(),
                'banner' => isset($gridBanner) ? $gridBanner : null
            ])->stringifyPartial('raw'),
            'pageUri' => $uri->output(),
            'isLastPage' => $src->isLastPage()
        ]);
    }
}