<?php

namespace SNOWGIRL_SHOP\Catalog;

use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_CORE\View\Widget\Pager;

use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Entity\Brand;
use SNOWGIRL_SHOP\Entity\Color;
use SNOWGIRL_SHOP\Entity\Country;
use SNOWGIRL_SHOP\Entity\Material;
use SNOWGIRL_SHOP\Entity\Season;
use SNOWGIRL_SHOP\Entity\Size;
use SNOWGIRL_SHOP\Entity\Tag;
use SNOWGIRL_SHOP\Entity\Vendor;

use SNOWGIRL_CORE\Helper;
use SNOWGIRL_CORE\Helper\Data as DataHelper;

use SNOWGIRL_SHOP\Entity\Category\Alias as CategoryAlias;
use Throwable;

/**
 * Available params:
 * site,
 * phone,
 * total,
 * prices,
 * query,
 * query_long,
 * query_keys,
 * category,
 * category_long,
 * category_keys,
 * brands,
 * brands_long,
 * brands_keys,
 * colors,
 * colors_long,
 * colors_keys,
 * seasons,
 * seasons_long,
 * seasons_keys,
 * materials,
 * materials_long,
 * materials_keys,
 * sizes,
 * sizes_long,
 * sizes_keys,
 * tags,
 * tags_long,
 * tags_keys,
 * countries,
 * countries_long,
 * countries_keys,
 * vendors,
 * vendors_long,
 * vendors_keys,
 * sport,
 * sport_long,
 * sport_keys,
 * size_plus,
 * size_plus_long,
 * size_plus_keys,
 * sales,
 * sales_long,
 * sales_keys,
 * order,
 * order_long,
 * order_keys,
 * page,
 * page_long,
 * Class SEO
 *
 * @package SNOWGIRL_SHOP\Catalog
 */
class SEO
{
    //@TODO h2 from keywords....
    //@todo split content with h2 headers....

    /**
     * @var URI
     */
    private $uri;
    private $params;
    private $retrieveAll = true;

    public function __construct(URI $uri)
    {
        $this->uri = $uri;
    }

    /**
     * @return array
     * @throws Throwable
     */
    public function getParams(): array
    {
        if (null === $this->params) {
            $this->params = [];

            $this->addCoreParams()
                ->addQueryParams()
                ->addCategoryParams()
                ->addBrandParams()
                ->addColorParams()
                ->addCountryParams()
                ->addMaterialParams()
                ->addSeasonParams()
                ->addSizeParams()
                ->addTagParams()
                ->addVendorParams()
                ->addTypeParams()
                ->addPriceParams()
                ->addViewParams()
                ->addCountParams();
        }

        return $this->params;
    }

    public function retrieveAll(bool $retrieveAll = true): SEO
    {
        $this->retrieveAll = !!$retrieveAll;

        return $this;
    }

    /**
     * @param string $attr
     * @param null $default
     * @param array $params
     * @return \DateTime|int|mixed|null|string
     * @throws Throwable
     */
    private function makeAttrValue(string $attr, $default = null, array $params = [])
    {
        if ($page = $this->uri->getSRC()->getCatalogPage()) {
            if ($page->hasAttr($attr) && $tmp = $page->get($attr)) {
                return $tmp;
            }
        }

        if ($pageCustom = $this->uri->getSRC()->getCatalogCustomPage()) {
            if ($pageCustom->hasAttr($attr) && $tmp = $pageCustom->get($attr)) {
                return $tmp;
            }
        }

        return $this->uri->getSRC()->getPage()->make($attr, $default, $params);
    }

    private function addCoreParams(): SEO
    {
        $this->params['site'] = $this->uri->getApp()->getSite('Shop Site');
        $this->params['phone'] = $this->uri->getApp()->config('site.phone');

        return $this;
    }

    private function addQueryParams(): SEO
    {
//        if ($query = $this->uri->get(URI::QUERY)) {
//            $this->params['query'] = $name = $query;
//            $this->params['query_long'] = 'поиск "' . $name . '"';
//            $this->params['query_keys'] = implode(',', array_map(function ($keyword) {
//                return mb_strtolower($keyword);
//            }, array_filter(array_merge(['Поиск'], explode(' ', $name)), function ($keyword) {
//                return 2 < strlen($keyword);
//            })));
//        }

        return $this;
    }

    public function getDefaultCategory()
    {
        return $this->uri->getApp()->config('catalog.default_category', 'Каталог');
    }

    /**
     * @return SEO
     * @throws Throwable
     */
    private function addCategoryParams(): SEO
    {
        $pk = Category::getPk();

        if ($id = $this->uri->get($pk)) {
            if ($alias = $this->uri->getSRC()->getAliasObject('category_id')) {
                $name = $alias->getTitle() ?: $alias->getName();
            }

            if (!isset($name)) {
                if (!$category = $this->uri->getApp()->managers->categories->find($id)) {
                    $this->uri->getApp()->container->logger->error('invalid category', [
                        'category_id' => $id,
                    ]);

                    return $this;
                }

                $name = $category->getTitle() ?: $category->getName();
            }
        } else {
            $name = $this->getDefaultCategory();
        }

        $this->params['category'] = $name;
        $this->params['category_long'] = 'Категория ' . $name;
        $this->params['category_keys'] = implode(',', array_map(function ($keyword) {
            return mb_strtolower($keyword);
        }, array_filter(explode(' ', $name), function ($keyword) {
            return 2 < strlen($keyword);
        })));

        return $this;
    }

    private function addBrandParams(): SEO
    {
        $pk = Brand::getPk();

        if (!$id = $this->uri->get($pk)) {
            return $this;
        }

        $id = is_array($id) ? $this->uri->getApp()->managers->brands->findMany($id) : [$id => $this->uri->getApp()->managers->brands->find($id)];

        $id = array_map(function ($brand) {
            /** @var Brand $brand */
            return $brand->getName();
        }, array_filter($id, function ($brand, $id) {
            if ($brand instanceof Brand) {
                return true;
            }

            $this->uri->getApp()->container->logger->warning('invalid brand', [
                'brand_id' => $id,
            ]);
            return false;
        }, ARRAY_FILTER_USE_BOTH));

        if (!$s = count($id)) {
            return $this;
        }

        $tmp = [];

        foreach ($id as $v) {
            $tmp += explode(' ', $v);
        }

        $this->params['brands_keys'] = implode(',', $tmp);

        if ($s > 2) {
            $last = array_pop($id);
            $id = [implode(', ', $id), $last];
        }

        $id = implode(' или ', $id);

        $this->params['brands'] = $id;
        $this->params['brands_long'] = 'от ' . $id;

        return $this;
    }

    private function addColorParams(): SEO
    {
        if (!$id = $this->uri->get(Color::getPk())) {
            return $this;
        }

        $id = is_array($id) ? $this->uri->getApp()->managers->colors->findMany($id) : [$id => $this->uri->getApp()->managers->colors->find($id)];

        $id = array_map(function ($color) {
            /** @var Color $color */
            return mb_strtolower($color->getNameMultiply() ?: $color->getName());
        }, array_filter($id, function ($color, $id) {
            if ($color instanceof Color) {
                return true;
            }

            $this->uri->getApp()->container->logger->error('invalid color', [
                'color_id' => $id,
            ]);
            return false;
        }, ARRAY_FILTER_USE_BOTH));

        if (!$s = count($id)) {
            return $this;
        }

        $this->params['colors_keys'] = implode(',', $id);

        if ($s > 2) {
            $last = array_pop($id);
            $id = [implode(', ', $id), $last];
        }

        $id = implode(' или ', $id);

        $this->params['colors'] = $id . ' цвета';
        $this->params['colors_long'] = $id . ' цвета';

        return $this;
    }

    private function addSeasonParams(): SEO
    {
        if (!$id = $this->uri->get(Season::getPk())) {
            return $this;
        }

        $id = is_array($id) ? $this->uri->getApp()->managers->seasons->findMany($id) : [$id => $this->uri->getApp()->managers->seasons->find($id)];

        $id = array_map(function ($season) {
            /** @var Season $season */
            return mb_strtolower($season->getName());
        }, array_filter($id, function ($season, $id) {
            if ($season instanceof Season) {
                return true;
            }

            $this->uri->getApp()->container->logger->error('invalid season', [
                'season_id' => $id,
            ]);
            return false;
        }, ARRAY_FILTER_USE_BOTH));

        if (!$s = count($id)) {
            return $this;
        }

        $this->params['seasons_keys'] = implode(',', $id);

        if ($s > 2) {
            $last = array_pop($id);
            $id = [
                implode(', ', $id),
                $last
            ];
        }

        $id = implode(' или ', $id);

        $this->params['seasons'] = $id;
        $this->params['seasons_long'] = 'Сезон ' . $id;

        return $this;
    }

    private function addMaterialParams(): SEO
    {
        if (!$id = $this->uri->get(Material::getPk())) {
            return $this;
        }

        $id = is_array($id) ? $this->uri->getApp()->managers->materials->findMany($id) : [$id => $this->uri->getApp()->managers->materials->find($id)];

        $id = array_map(function ($material) {
            /** @var Material $material */
            return mb_strtolower($material->getName());
        }, array_filter($id, function ($material, $id) {
            if ($material instanceof Material) {
                return true;
            }

            $this->uri->getApp()->container->logger->error('invalid material', [
                'material_id' => $id,
            ]);
            return false;
        }, ARRAY_FILTER_USE_BOTH));

        if (!$s = count($id)) {
            return $this;
        }

        $this->params['materials_keys'] = implode(',', $id);

        if ($s > 2) {
            $last = array_pop($id);
            $id = [implode(', ', $id), $last];
        }

        $id = implode(' или ', $id);

        $this->params['materials'] = $id;
        $this->params['materials_long'] = 'Материал ' . $id;

        return $this;
    }

    private function addSizeParams(): SEO
    {
        if (!$id = $this->uri->get(Size::getPk())) {
            return $this;
        }

        $id = is_array($id) ? $this->uri->getApp()->managers->sizes->findMany($id) : [$id => $this->uri->getApp()->managers->sizes->find($id)];

        $id = array_map(function ($size) {
            /** @var Size $size */
            return mb_strtolower($size->getName());
        }, array_filter($id, function ($size, $id) {
            if ($size instanceof Size) {
                return true;
            }

            $this->uri->getApp()->container->logger->error('invalid size', [
                'size_id' => $id,
            ]);
            return false;
        }, ARRAY_FILTER_USE_BOTH));

        if (!$s = count($id)) {
            return $this;
        }

        $this->params['sizes_keys'] = implode(',', $id);

        if ($s > 2) {
            $last = array_pop($id);
            $id = [implode(', ', $id), $last];
        }

        $id = implode(' или ', $id);

        $this->params['sizes'] = $id;
        $this->params['sizes_long'] = 'Размер ' . $id;

        return $this;
    }

    private function addTagParams(): SEO
    {
        if (!$id = $this->uri->get(Tag::getPk())) {
            return $this;
        }

        $id = is_array($id) ? $this->uri->getApp()->managers->tags->findMany($id) : [$id => $this->uri->getApp()->managers->tags->find($id)];

        $id = array_map(function ($tag) {
            /** @var Tag $tag */
            return mb_strtolower($tag->getName());
        }, array_filter($id, function ($tag, $id) {
            if ($tag instanceof Tag) {
                return true;
            }

            $this->uri->getApp()->container->logger->error('invalid tag', [
                'tag_id' => $id,
            ]);
            return false;
        }, ARRAY_FILTER_USE_BOTH));

        if (!$s = count($id)) {
            return $this;
        }

        $this->params['tags_keys'] = implode(',', $id);

        if ($s > 2) {
            $last = array_pop($id);
            $id = [implode(', ', $id), $last];
        }

        $id = implode(' или ', $id);

        $this->params['tags'] = $id;
        $this->params['tags_long'] = 'Теги ' . $id;

        return $this;
    }

    private function addCountryParams(): SEO
    {
        if (!$id = $this->uri->get(Country::getPk())) {
            return $this;
        }

        $id = is_array($id) ? $this->uri->getApp()->managers->countries->findMany($id) : [$id => $this->uri->getApp()->managers->countries->find($id)];

        $id = array_map(function ($country) {
            /** @var Country $country */
            return $country->getName();
        }, array_filter($id, function ($country, $id) {
            if ($country instanceof Country) {
                return true;
            }

            $this->uri->getApp()->container->logger->error('invalid country', [
                'country_id' => $id,
            ]);
            return false;
        }, ARRAY_FILTER_USE_BOTH));

        if (!$s = count($id)) {
            return $this;
        }

        $this->params['countries_keys'] = implode(',', $id);

        if ($s > 2) {
            $last = array_pop($id);
            $id = [implode(', ', $id), $last];
        }

        $id = implode(' или ', $id);

        $this->params['countries'] = $id;
        $this->params['countries_long'] = 'Производство ' . $id;

        return $this;
    }

    private function addVendorParams(): SEO
    {
        if (!$id = $this->uri->get(Vendor::getPk())) {
            return $this;
        }

        $id = is_array($id) ? $this->uri->getApp()->managers->vendors->findMany($id) : [$id => $this->uri->getApp()->managers->vendors->find($id)];

        $id = array_map(function ($vendor) {
            /** @var Vendor $vendor */
            return DataHelper::ucFirst($vendor->getName());
        }, array_filter($id, function ($vendor, $id) {
            if ($vendor instanceof Vendor) {
                return true;
            }

            $this->uri->getApp()->container->logger->error('invalid vendor', [
                'vendor_id' => $id,
            ]);
            return false;
        }, ARRAY_FILTER_USE_BOTH));

        if (!$s = count($id)) {
            return $this;
        }

        $this->params['vendors_keys'] = implode(',', $id);

        if ($s > 2) {
            $last = array_pop($id);
            $id = [implode(', ', $id), $last];
        }

        $id = implode(' или ', $id);

        $this->params['vendors'] = $id;
        $this->params['vendors_long'] = 'магазин' . ($s > 1 ? 'ы' : '') . ' ' . $id;

        return $this;
    }

    private function addTypeParams(): SEO
    {
        if ($this->uri->get(URI::SPORT)) {
            $typesToParams = self::getTypesToParams();
            $key = $typesToParams[URI::SPORT];
            $this->params[$key] = 'для спорта и отдыха';
            $this->params[$key . '_long'] = 'для спорта и отдыха';
            $this->params[$key . '_keys'] = 'спорт,отдых,досуг,бег,фитнес,ходьба,занятия';
        }

        if ($this->uri->get(URI::SIZE_PLUS)) {
            $typesToParams = isset($typesToParams) ? $typesToParams : self::getTypesToParams();
            $key = $typesToParams[URI::SIZE_PLUS];
            $this->params[$key] = 'большие размеры';
            $this->params[$key . '_long'] = 'большие размеры';
            $this->params[$key . '_keys'] = 'большие,размеры,оверсайз';
        }

        if ($this->uri->get(URI::SALES)) {
            $typesToParams = isset($typesToParams) ? $typesToParams : self::getTypesToParams();
            $key = $typesToParams[URI::SALES];
            $this->params[$key] = 'со скидками';
            $this->params[$key . '_long'] = 'только со скидками';
            $this->params[$key . '_keys'] = 'скидки,распродажа';
        }

        return $this;
    }

    private function addPriceParams(): SEO
    {
        $name = [];

        if (!$from = $this->uri->get(URI::PRICE_FROM)) {
            if ($this->retrieveAll) {
                $prices = $this->uri->getSRC()->getItemsPricesRange();
                $from = $prices['min'];
            }
        }

        if ($from) {
            $name[] = 'от ' . $from;
        }

        if (!$to = $this->uri->get(URI::PRICE_TO)) {
            if ($this->retrieveAll) {
                $prices = isset($prices) ? $prices : $this->uri->getSRC()->getItemsPricesRange();
                $to = $prices['max'];
            }
        }

        if ($to) {
            $name[] = 'до ' . $to;
        }

        if ($name) {
            $name[] = $this->uri->getApp()->trans->makeText('catalog.currency_' . $this->uri->getApp()->config('catalog.currency', 'RUB'));
            $this->params['prices'] = implode(' ', $name);
        }

        return $this;
    }

    private function addViewParams(): SEO
    {
        if ($value = $this->uri->get(URI::ORDER)) {
            $viewsToParams = self::getViewsToParams();
            $key = $viewsToParams[URI::ORDER];
            $values = self::getOrderValuesToTexts();
            $this->params[$key] = $name = $values[$value];
            //@todo...
            $this->params[$key . '_long'] = $name;
            $this->params[$key . '_keys'] = implode(',', explode(' ', $name));
        }

        return $this;
    }

    /**
     * @return SEO
     * @throws Throwable
     */
    private function addCountParams(): SEO
    {
        $num = $this->uri->getSRC()->getPageNum();

        if ($page = $this->uri->getSRC()->getCatalogPage()) {
            $this->params['total'] = $page->getMetaKey('count');
        }

        if ($this->retrieveAll) {
            if (!isset($this->params['total'])) {
                $this->params['total'] = $this->uri->getSRC()->getTotalCount();
            }

            if (1 < $num) {
                $last = $this->uri->getSRC()->getLastPage();

                if (1 < $last) {
                    $this->params['page'] = 'Страница ' . $num;
                    $this->params['page_long'] = $this->params['page'] . ' из ' . $last;
                }
            }
        } else {
            if (!isset($this->params['total'])) {
                $this->params['total'] = $num * $this->uri->getSRC()->getLimit();
            }

            if (1 < $num) {
                $this->params['page'] = 'Страница ' . $num;
            }
        }

        return $this;
    }

    /**
     * @param string $key
     * @param array $params
     * @param bool $nice
     * @return \DateTime|int|mixed|null|string|string[]
     * @throws Throwable
     */
    public function getParam(string $key, array $params = [], bool $nice = true)
    {
        $params = array_merge($this->getParams(), $params);
        $output = $this->makeAttrValue($key, $params[$key] ?? null, $params);

        if ($nice) {
            $output = Helper::getNiceSemanticText($output);
        }

        return $output;
    }

    /**
     * @param Layout $view
     * @param array $params
     * @return SEO
     * @throws Throwable
     */
    public function managePage(Layout $view, array $params = []): SEO
    {
        if ($pageCatalog = $this->uri->getSRC()->getCatalogPage()) {
            $reqUri = $this->uri->output(URI::OUTPUT_FULL, true);
            $rawReqUri = $this->uri->getApp()->request->getUri();

            //normalized and de-normalized are different
            if ($rawReqUri != $reqUri) {
                $this->uri->getApp()->request->redirect($reqUri, 301);
            }

            $copy = $this->uri->copy();

            //if default page
            if (1 == $this->uri->get(URI::PAGE_NUM)) {
                $copy->set(URI::PAGE_NUM, null);
            }

            //if default per page value
            if (SRC::getDefaultShowValue($this->uri->getApp()) == $this->uri->get(URI::PER_PAGE)) {
                $copy->set(URI::PER_PAGE, null);
            }

            //if default order value
            if (SRC::getDefaultOrderValue() == $this->uri->get(URI::ORDER)) {
                $copy->set(URI::ORDER, null);
            }

            $canUri = $copy->output(URI::OUTPUT_DEFINED, true);

            //safe or extra params
            if ($reqUri != $canUri) {
                $view->setCanonical($canUri);
            }
        }

        $params = array_merge($params, $this->getParams());

        $view->setTitle($title = Helper::getNiceSemanticText($this->makeAttrValue('meta_title', $params['meta_title'] ?? null, $params)))
            ->setH1(Helper::getNiceSemanticText($this->makeAttrValue('h1', $params['h1'] ?? $title, $params)))
            ->setHeadPrefix('og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# product: http://ogp.me/ns/product#')
            ->addMetaProperty('fb:app_id', $this->uri->getApp()->config('keys.facebook_app_id'))
            ->addMetaProperty('og:site_name', $this->uri->getApp()->getSite())
//            ->addMetaProperty('og:locale:locale', strtolower($this->uri->getApp()->trans->getLocale()))
            ->addMetaProperty('og:type', 'product.group')
            ->addMetaProperty('og:url', isset($canUri) ? $canUri : $this->uri->output(URI::OUTPUT_DEFINED, true))
            ->addMetaProperty('og:title', Helper::getNiceSemanticText($this->makeAttrValue('meta_og_title', $params['meta_og_title'] ?? $title, $params)));

        //@todo change when categories (or categories-tags pairs or other combinations) has images...
        if (($item = $this->uri->getSRC()->getFirstItem(true)) && ($image = $this->uri->getApp()->images->get($item->getImage()))) {
            $view->addMetaProperty('og:image', $imageLink = $this->uri->getApp()->images->getLink($image))
                ->addMetaProperty('og:image:secure_url', $imageLink)
                ->addMetaProperty('og:image:type', $image->getMime())
                ->addMetaProperty('og:image:width', $image->getWidth())
                ->addMetaProperty('og:image:height', $image->getHeight())
                ->addMetaProperty('og:image:alt', 'Фото ' . $this->uri->getApp()->managers->items->getCategory($item)->getName())
                ->addMetaProperty('og:image:user_generated', 'false')
                ->addHeadLink('image', $imageLink);
        }

        $view->addMeta('description', $description = Helper::getNiceSemanticText($this->makeAttrValue('meta_description', $params['meta_description'] ?? null, $params)))
            ->addMetaProperty('og:description', Helper::getNiceSemanticText($this->makeAttrValue('meta_og_description', $params['meta_og_description'] ?? $description, $params)))
            ->addMeta('keywords', Helper::getNiceSemanticText($this->makeAttrValue('meta_keywords', $params['meta_keywords'] ?? null, $params)));

        if ($pageCatalog && $pageCatalogCustom = $this->uri->getSRC()->getCatalogCustomPage()) {
            $articles = array_filter($pageCatalogCustom->getSeoTexts(true), function ($article) {
                return $article['active'];
            });

            if (0 < count($articles)) {
                $view->about = (string) $this->uri->getApp()->views->get('@shop/catalog/about.phtml', [
                    'articles' => $articles,
                    'site' => $this->uri->getApp()->getSite(),
                    'uri' => isset($canUri) ? $canUri : $this->uri->output(URI::OUTPUT_DEFINED, true),
                ]);
            }
        }

        return $this;
    }

    /**
     * @param Pager $pager
     * @param Layout $view
     * @return SEO
     * @throws Throwable
     */
    public function managePager(Pager $pager, Layout $view): SEO
    {
        $page = $pager->getCurrentPageNumber();
        $copy = $this->uri->copy();

        if ($page < $pager->getTotalPageCount()) {
            $newPage = $page + 1;
            $view->addHeadLink('next', $copy->set(URI::PAGE_NUM, $newPage)->output());
        }

        if ($page > 1) {
            $newPage = $page - 1;
            $newPage = 1 == $newPage ? null : $newPage;

            $view->addHeadLink('prev', $copy->set(URI::PAGE_NUM, $newPage)->output());
        }

//        if (1 == $page) {
//            $view->setCanonical($copy->set(URI::PAGE_NUM, null)->output());
//        }

        return $this;
    }

    /**
     * @param $h1ParamsSize
     * @param Layout $view
     * @throws Throwable
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function manageBreadcrumbs($h1ParamsSize, Layout $view)
    {
        $categoryId = $this->uri->get(Category::getPk());

        if ($categoryId) {
            $view->addBreadcrumb($this->uri->getApp()->trans->makeText('catalog.catalog'), (string) new URI);

            $category = $this->uri->getApp()->managers->categories->find($categoryId);

            foreach ($this->uri->getApp()->managers->categories->getChainedParents($category) as $id => $parentCategory) {
                $view->addBreadcrumb($parentCategory->getBreadcrumb() ?: $parentCategory->getName(), $this->uri->getApp()->managers->categories->getLink($parentCategory));
            }

            /** @var CategoryAlias $alias */
            if ($alias = $this->uri->getSRC()->getAliasObject('category_id')) {
                $name = $alias->getBreadcrumb() ?: $alias->getName();
            }

            if (!isset($name) || !$name) {
                $name = $category->getBreadcrumb() ?: $category->getName();
            }

            if (0 == $h1ParamsSize) {
                $categoryBreadcrumb = $name;
            } else {
                $view->addBreadcrumb($name, $this->uri->getApp()->managers->categories->getLink($category));
                $categoryBreadcrumb = '';
            }
        } else {
            if (0 == $h1ParamsSize) {
                $categoryBreadcrumb = $this->uri->getApp()->trans->makeText('catalog.catalog');
            } else {
                $view->addBreadcrumb($this->uri->getApp()->trans->makeText('catalog.catalog'), (string) new URI);
                $categoryBreadcrumb = '';
            }
        }

//        $view->addBreadcrumb($view->getH1());
        $view->addBreadcrumb($this->getParam('h1', ['category' => $categoryBreadcrumb]));
    }

    public static function getTypesToParams(): array
    {
        return array_merge(array_combine(URI::TYPE_PARAMS, URI::TYPE_PARAMS), [
            URI::SPORT => 'sport',
            URI::SIZE_PLUS => 'size_plus',
            URI::SALES => 'sales'
        ]);
    }

    public static function getTypesToTexts($catalog = false): array
    {
        if ($catalog) {
            return array_merge(array_combine(URI::TYPE_PARAMS, array_fill(0, count(URI::TYPE_PARAMS), null)), [
                URI::SPORT => 'Спорт и отдых',
                URI::SIZE_PLUS => 'Большие размеры',
                URI::SALES => '% Скидки'
            ]);
        }

        return array_merge(array_combine(URI::TYPE_PARAMS, array_fill(0, count(URI::TYPE_PARAMS), null)), [
            URI::SPORT => 'спорт и отдых',
            URI::SIZE_PLUS => 'большие размеры',
            URI::SALES => 'скидки'
        ]);
    }

    public static function getViewsToParams(): array
    {
        return array_merge(array_combine(URI::VIEW_PARAMS, URI::VIEW_PARAMS), [
            URI::ORDER => 'order',
            URI::PER_PAGE => 'per_page',
            URI::PAGE_NUM => 'page_num'
        ]);
    }

    public static function getOrderValuesToTexts($catalog = false): array
    {
        $values = SRC::getOrderValues();

        if ($catalog) {
            return array_merge(array_combine($values, array_fill(0, count($values), null)), [
                '-relevance' => 'Релевантности',
                '-rating' => 'Рейтингу',
                'price' => 'Возрастанию цены',
                '-price' => 'Убыванию цены'
            ]);
        }

        return array_merge(array_combine($values, array_fill(0, count($values), null)), [
            '-relevance' => 'отсортировано по релевантности',
            '-rating' => 'отсортировано по рейтингу',
            'price' => 'отсортировано по возрастанию цены',
            '-price' => 'отсортировано по убыванию цены'
        ]);
    }
}
