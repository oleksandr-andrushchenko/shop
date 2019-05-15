<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/15/19
 * Time: 12:18 AM
 */

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_SHOP\Catalog\SEO;
use SNOWGIRL_SHOP\Catalog\SRC;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Catalog\URI\Manager as CatalogUriManager;
use SNOWGIRL_CORE\View\Widget\Ad\LongHorizontal as LongHorizontalAd;
use SNOWGIRL_CORE\View\Widget\Ad\LargeRectangle as LargeRectangleAd;
use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\View\Widget\Grid\Items as ItemsGrid;

class CatalogAction
{
    use PrepareServicesTrait;
    use GetCurrencyObjectTrait;
    use GetFiltersCountsObjectTrait;

    /**
     * @param App $app
     *
     * @return bool
     * @throws \SNOWGIRL_CORE\Exception
     * @throws \SNOWGIRL_CORE\Exception\HTTP\Forbidden
     * @throws \Exception
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $key = 'catalog';

        $app->managers->categories->clear();

        $app->services->mcms->prefetch([
            $app->managers->categories->getAllIDsCacheKey(),
            $app->managers->pagesRegular->getMenuCacheKey(),
        ]);

        //cache all categories...
        $app->managers->categories->findAll();

        $app->request->set('uri', null);

        $uriManager = new CatalogUriManager($app);

        if (!$uri = $uriManager->createFromRequest($app->request)) {
            return false;
        }

        $app->analytics->logPageHit($key);

        $view = $app->views->getLayout();

        $mobile = $app->request->getDevice()->isMobile();

        if ((!$mobile) && $gridBanner = $app->ads->findBanner(LargeRectangleAd::class, 'catalog-grid', [], $view)) {
            $uri->getSRC()->setLimit($uri->getSRC()->getLimit() - ItemsGrid::getBannerCellsCount());
        }

        $app->seo->manageCatalogPage($uri, $view, [
            'meta_title' => '{category} {sport} {size_plus} {tags} {sizes} {brands} {colors}  {materials} {seasons} {countries} {sales} {vendors} купить {prices} с доставкой по РФ, выбор среди {total} предложений. {page_long}',
            'meta_description' => 'Купить {category_long} {sport_long} {size_plus_long} {tags_long} {brands_long}. {sizes_long}. {colors_long}. {sales_long}. {materials_long}. {seasons_long}. {countries_long}. {vendors_long}. {prices}. Более {total} предложений ✔ Доставка по РФ ✔ Низкие цены ✔ Возврат ✔ Комфортная и надежная покупка! *** Тел. {phone} ***. {page_long}',
            'meta_keywords' => '{category_keys},{sport_keys},{size_plus_keys},{tags_keys},{brands_keys},купить,{colors_keys},{sales_keys},{seasons_keys},{countries_keys},{sizes_keys},{vendors_keys}',
            'h1' => '{category} {sport} {size_plus} {tags} {brands} {sizes} {colors} {materials} {seasons} {countries} {vendors} {sales}',
            'description' => '{category} {sport} {size_plus} {tags} {brands} {sizes} {colors} {materials} {seasons} {countries} {vendors} {sales}',
        ]);
        $app->analytics->logCatalogPageHit($uri);

        $filtersNames = array_diff($app->managers->catalog->getComponentsPKs(), [Category::getPk()]);

        $items = $uri->getSRC()->getItems($total);

        $content = $view->setContentByTemplate('@shop/catalog.phtml', [
            'uri' => $uri->output(),
            'uriParams' => $uriParams = $uri->getParamsArray(),
            'h1' => $view->getH1(),
            'currency' => $this->getCurrencyObject($app),
            'filters' => $filters = $uri->getParamsByNames($filtersNames),
            'prices' => $prices = $uri->getParamsByNames([URI::PRICE_FROM, URI::PRICE_TO]),
            'uriFilterParams' => $uri->getParamsByTypes('filter'),
            'uriViewParams' => $uri->getParamsByTypes('view'),
            'categories' => $app->managers->categories->makeTreeHtml($uri),
            'sortValues' => SRC::getOrderValues(),
            'sortValuesNames' => SEO::getOrderValuesToTexts(true),
            'typesNames' => SEO::getTypesToTexts(true),
            'types' => $types = array_keys($uri->getParamsByNames(URI::TYPE_PARAMS)),
//            'hasAppliedFilters' => $hasAppliedFilters = $uriParams[URI::QUERY] || count($filters) > 0 || count($prices) > 0 || count($types) > 0,
            'hasAppliedFilters' => $hasAppliedFilters = count($filters) > 0 || count($prices) > 0 || count($types) > 0,
            'hasAppliedSorting' => $hasAppliedSorting = !!$uriParams[URI::ORDER],
            'orFiltersKeys' => $uri->getOrParamsKeysByNames($filtersNames),
            'hasItems' => $total > 0,
            'totalCount' => $total,
            'siteName' => $app->getSite()
        ]);

        //@todo...
        if (!$mobile) {
            $content->showValues = SRC::getShowValues($app);
        }

        if ($content->hasItems) {
            $content->itemsGrid = $app->views->items([
                'items' => $items,
                'offset' => $uri->getSRC()->getOffset(),
                'propName' => $content->title,
                'propUrl' => (new URI($uriParams))->_unset(URI::PAGE_NUM)->output(),
                'propTotal' => $content->totalCount,
                'banner' => isset($gridBanner) ? $gridBanner : null
            ], $view);

            $pagerUri = $uri->copy();

            $pager = $app->views->pager([
                'link' => $pagerUri->set(URI::PAGE_NUM, '{' . URI::PAGE_NUM . '}')->output(),
                'total' => $total,
                'size' => $uri->getSRC()->getLimit(),
                'page' => $uri->getSRC()->getPageNum(),
                'per_set' => 5,
                'param' => URI::PAGE_NUM,
//                'attrs' => 'rel="nofollow"',
                'statistic' => false
            ], $view);

            if ($pager->isOk() && !$mobile) {
                $content->itemsPager = (string)$pager;
            }

            $content->addParams([
//                'showAjaxPager' => !$pager->isLastPage(),
                'showAjaxPager' => $pager->isOk()
            ]);

            $app->seo->manageCatalogPager($pagerUri, $pager, $view);
        } else {
            $content->itemsEmptyVariants = $uriManager->getOtherVariants($uri, function ($uri) use ($app, $view) {
                /** @var URI $uri */
                return (object)[
                    'href' => $href = $uri->output(),
                    'text' => $text = $uri->getSEO()->getParam('h1', [
                        'category' => ($tmp = $uri->get(Category::getPk()))
                            ? $app->managers->categories->find($tmp)->getName()
                            : $app->trans->makeText('catalog.catalog')
                    ]),
                    'grid' => $app->views->items([
                        'items' => array_slice($uri->getSRC()->getItems($total), 0, 4),
                        'itemTemplate' => 'catalog',
                        'propName' => $text,
                        'propUrl' => $href,
                        'propTotal' => $total
                    ], $view),
                    'count' => $total
                ];
            });

            $content->itemsPager = '';
        }

        $view->addJsConfig('searchQuery', $content->title);

        if ($categoryId = $uri->get(Category::getPk())) {
            $category = $app->managers->categories->find($categoryId);
        } else {
            $category = null;
        }

        $app->seo->manageCatalogBreadcrumbs($uri, count($filters) + count($types), $view);

        $content->addParams([
            'category' => $category,
            'hasApplied' => $categoryId || $hasAppliedFilters || $hasAppliedSorting,
            'showTags' => $categoryId && $app->managers->categories->isLeaf($category),
            'vkontakteLike' => $app->views->vkontakteLike($view)->stringify(),
            'facebookLike' => $app->views->facebookLike($view)->stringify(),
            'asyncFilters' => $app->config->catalog->async_filters(false)
        ]);

        if ($content->hasItems) {
            $brands = null;

            $content->addParams([
                'filtersCounts' => $this->getFiltersCountsObject($app),
                'filtersTypesView' => (new GetCatalogFiltersTypesView)($app, $uri, $content->asyncFilters),
                'filtersTagsView' => $content->showTags ? (new GetCatalogFiltersTagsView)($app, $uri, $content->asyncFilters) : null,
                'filtersBrandsView' => (new GetCatalogFiltersBrandsView)($app, $uri, $content->asyncFilters, $brands),
                'filtersCountriesView' => (new GetCatalogFiltersCountriesView)($app, $uri, $content->asyncFilters),
                'filtersVendorsView' => (new GetCatalogFiltersVendorsView)($app, $uri, $content->asyncFilters),
                'filtersPricesView' => (new GetCatalogFiltersPricesView)($app, $uri, $content->asyncFilters),
                'filtersColorsView' => (new GetCatalogFiltersColorsView)($app, $uri, $content->asyncFilters),
                'filtersSeasonsView' => (new GetCatalogFiltersSeasonsView)($app, $uri, $content->asyncFilters),
                'filtersMaterialsView' => (new GetCatalogFiltersMaterialsView)($app, $uri, $content->asyncFilters),
                'filtersSizesView' => (new GetCatalogFiltersSizesView)($app, $uri, $content->asyncFilters),
            ]);

            if (!$mobile) {
                $tmpParams = $uri->getParamsByTypes('filter');

                if (!Arrays::diffByKeysArray($tmpParams, ['category_id', URI::SALES])) {
                    //@todo replace filter with sort

                    if (isset($tmpParams['category_id'])) {
                        $category = $app->managers->categories->find($tmpParams['category_id']);

                        if ($app->managers->categories->isLeaf($category)) {
                            //@todo available only
                            //@todo retrieve top category-tags pairs
//                        $popularCategoryTags = [];
                        } else {
                            //@todo available only
                            $popularCategories = $app->managers->categories->getDirectChildrenObjects($category);
                        }
                    } else {
                        $popularCategories = $app->managers->categories->getRootObjects();
                    }

                    $tmpUri = new URI($tmpParams);

                    if (isset($popularCategories)) {
                        $popularCategories = array_filter($popularCategories, function ($category) use ($app) {
                            return 0 < $app->managers->categories->getItemsCount($category);
                        });

                        $app->managers->categories->sortByRating($popularCategories);

                        //@todo cache...
                        $content->categoriesGrid = $app->views->categories([
                            //hidden coz: 1) items counts 2) increase clicks
//                        'uri' => $tmpUri->copy()->_unset('category_id'),
                            'items' => array_slice($popularCategories, 0, 6)
                        ], $content);
                    }

                    //@todo cache...
                    $content->brandsGrid = $app->views->brands([
                        'uri' => $tmpUri,
                        'items' => isset($brands) && !$content->asyncFilters
                            ? array_slice($brands, 0, 12)
                            : $app->managers->brands->clear()
                                ->setOrders(['rating' => SORT_DESC])
                                ->setLimit(12)
                                ->getObjectsByUri($tmpUri)
                    ], $content);
                }
            }
        }

        if ((!$mobile) && $h1Banner = $app->ads->findBanner(LongHorizontalAd::class, 'catalog-title', [], $view)) {
            $content->h1Banner = $h1Banner;
        }

        $app->response->setHTML(200, $view);
    }
}