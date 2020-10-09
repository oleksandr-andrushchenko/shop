<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Helper;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Entity\Vendor;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_SHOP\Catalog\SRC;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Item\URI\Manager as ItemUriManager;
use SNOWGIRL_SHOP\View\Widget\Grid\Items as ItemsGrid;
use SNOWGIRL_CORE\View\Widget\Ad\LargeRectangle as LargeRectangleAd;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use Throwable;

class ItemAction
{
    use PrepareServicesTrait;
    use GetCurrencyObjectTrait;

    /**
     * @param App $app
     * @return bool
     * @throws Throwable
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $key = 'item';

        $app->container->cache->getMulti([
            $app->managers->categories->getAllIDsCacheKey(),
            $app->managers->pages->getItemCacheKey($key),
            $app->managers->pages->getMenuCacheKey(),
//            $app->managers->vendors->getCacheKeyById($item->getVendorId())
        ]);

        //cache all categories...
        $app->managers->categories->findAll();

        $app->request->set('uri', null);

        $uriManager = new ItemUriManager($app);

        if (!$uri = $uriManager->createFromRequest($app->request)) {
            return false;
        }

        $app->analytics->logPageHit($key);

        $view = $app->views->getLayout();

        $app->seo->manageItemPage($uri, $view, [
            'meta_title' => '{name} – купить за {price} руб. в интернет-магазине: цена, фото и описание. Арт. {partner_item_id}',
            'meta_description' => '{description} ✔ интернет-магазин {site} *** Тел: {phone} ✔ Доставка ✔ Гарантия ✔ Лучшие цены! Артикул № {partner_item_id}',
            'meta_keywords' => 'купить, {keywords}, женские, распродажа, дешево',
            'h1' => '{name}',
            'description' => '{name}',
        ]);

        $app->analytics->logItemPageHit($uri);

        if (!$item = $uri->getSRC()->getItem()) {
            throw new NotFoundHttpException();
        }

        $app->managers->items->clear();

        /**
         * @var Vendor $vendor
         * @var Vendor $replacedActiveVendor
         */

        $vendor = $app->managers->items->getVendor($item);

        if ($replaceVendor = $app->config('catalog.replace_vendor', [])) {
            if (!empty($replaceVendor[$vendor->getId()])) {
                $replacedActiveVendor = $app->managers->vendors->find($replaceVendor[$vendor->getId()]);

                if (!$replacedActiveVendor->isActive()) {
                    unset($replacedActiveVendor);
                }
            }
        }

        if ($fallbackVendor = $app->config('catalog.fallback_vendor', [])) {
            $fallbackActiveVendor = $app->managers->vendors->find($fallbackVendor);

            if (!$fallbackActiveVendor->isActive()) {
                unset($fallbackActiveVendor);
            }
        }

        $content = $view->setContentByTemplate('@shop/item.phtml', [
            'item' => $item,
            'vendor' => $vendor,
            'replacedActiveVendor' => $replacedActiveVendor ?? null,
            'fallbackActiveVendor' => $fallbackActiveVendor ?? null,
            'images' => $app->managers->items->getImages($item),
            'h1' => $uri->getSEO()->getParam('h1'),
            'currency' => $this->getCurrencyObject($app),
            'tags' => $app->managers->items->getTagsURI($item),
            'types' => $app->managers->items->getTypes($item),
            'attrs' => $app->managers->items->getAttrs($item),
            'deviceDesktop' => $app->request->getDevice()->isDesktop(),
            'archive' => $archive = $item->get('archive'),
            'typeOwn' => (!$archive) && ($source = $app->managers->items->getImportSource($item)) && (ImportSource::TYPE_OWN == $source->getType()),
            'outOfStockBuyButton' => $outOfStockBuyButton = !!$app->config('catalog.out_of_stock_buy_button', false),
            'inStockCheck' => !$outOfStockBuyButton && !!$app->config('catalog.in_stock_check', false) && $vendor->isInStockCheck(),
            'fallbackVendor' => $app->config('catalog.fallback_vendor'),
        ]);

        $relatedUri = $app->managers->items->getRelatedCatalogURI($item)
            ->set(URI::PER_PAGE, Helper::roundUpToAny(SRC::getDefaultShowValue($app), 5))
            ->set(URI::EVEN_NOT_STANDARD_PER_PAGE, true);

        if ((!$app->request->getDevice()->isMobile()) && $gridBanner = $app->ads->findBanner(LargeRectangleAd::class, 'item-catalog-grid', [], $view)) {
            $relatedUri->getSRC()->setLimit($relatedUri->getSRC()->getLimit() - ItemsGrid::getBannerCellsCount());
        }

        $relatedItems = $relatedUri->getSRC()->getItems($total);

        $content->hasRelatedItems = $total > 0;

        if ($content->hasRelatedItems) {
            $relatedItemsH1 = $relatedUri->getSEO()->getParam('h1');

            $content->relatedItemsGrid = $app->views->items([
                'h21' => 'Похожие ' . mb_strtolower($relatedItemsH1),
                'items' => $relatedItems,
                'propName' => $relatedItemsH1,
                'propUrl' => $relatedUri->copy()->_unset(URI::PER_PAGE)->output(),
                'propTotal' => $total,
                'banner' => isset($gridBanner) ? $gridBanner : null,
            ], $view);

            $content->relatedItemsPager = $total > $relatedUri->getSRC()->getLimit();
        }

        $content->addParams([
            'relatedUriFilterParams' => $relatedUri->getParamsByTypes('filter'),
            'relatedUriViewParams' => $relatedUri->getParamsByTypes('view'),
        ]);

        $app->seo->manageItemBreadcrumbs($uri, $view);

        $app->response->setHTML(200, $view);

        return true;
    }
}