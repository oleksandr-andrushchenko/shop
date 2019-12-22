<?php

namespace SNOWGIRL_SHOP\Item;

use SNOWGIRL_CORE\App;
use SNOWGIRL_CORE\Helper;
use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_CORE\Entity\Page;
use SNOWGIRL_SHOP\Catalog\URI as CatalogURI;

/**
 * @todo    add sales param in case of discount..
 *
 * Available params:
 *
 * *item table attr[]
 * site,
 * phone,
 * category,
 * brand,
 * partner_item_id (?? universal product code ??)
 * description
 * keywords
 *
 * Class SEO
 * @package SNOWGIRL_SHOP\Item
 */
class SEO
{
    /** @var App */
    protected $app;

    /** @var URI */
    protected $uri;
    protected $params;

    public function __construct(URI $uri)
    {
        $this->uri = $uri;
        $this->app = App::$instance;
    }

    protected function getParams()
    {
        if ($this->params) {
            return $this->params;
        }

        $item = $this->uri->getSRC()->getItem();

        $this->params = $item->getAttrs();

        if ($category = $this->app->managers->items->getCategory($item)) {
            $categoryName = $category->getName();
        } else {
            $categoryName = 'Item';
        }

        if ($brand = $this->app->managers->items->getBrand($item)) {
            $brandName = $brand->getName();
        } else {
            $brandName = 'Awesome';
        }

        $this->params = array_merge($this->params, [
            'site' => $this->app->getSite(),
            'phone' => $this->app->config->site->phone,
            'category' => $categoryName,
            'brand' => $brandName,
//            'color' => ($v = $this->app->managers->items->getColor($this->getItem())) ? $v->getName() : '',
            'partner_item_id' => $item->getPartnerItemId()
        ]);

        $this->params['description'] = implode('. ', [
            $item->getName(),
//            ($v = $this->getItem()->getColor()) ? ('Цвет ' . $v->getName()) : '',
            ($v = $this->app->managers->items->getCountry($item)) ? ('Производитель ' . $v->getName()) : '',
        ]);

        $keywords = [];
        $keywords += explode(' ', $item->getName());
        $keywords += explode(' ', $brand);
//        $keywords[] = ($v = $this->getItem()->getColor()) ? $v->getName() : '';
        $this->params['keywords'] = implode(',', $keywords);

        return $this->params;
    }

    /** @var  Page */
    protected $page;

    protected function getPage()
    {
        return $this->page ?: $this->page = $this->app->managers->pages->findByKey('item');
    }

    protected function makeAttrValue($attr, $default = null, array $params = [])
    {
        return $this->getPage()->make($attr, $default, $params);
    }

    public function getParam($key, array $params = [], $nice = true)
    {
        $params = array_merge($this->getParams(), $params);
        $output = $this->makeAttrValue($key, $params[$key] ?? null, $params);

        if ($nice) {
            $output = Helper::getNiceSemanticText($output);
        }

        return $output;
    }

    /**
     * @see https://developers.facebook.com/docs/reference/opengraph/object-type/product.item/
     *
     * @param Layout $view
     * @param array  $params
     *
     * @return $this|SEO
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function managePage(Layout $view, array $params = [])
    {
        $reqUri = $this->uri->output(URI::OUTPUT_FULL);
        $rawReqUri = $this->app->request->getUri();

        //normalized and de-normalized are different
        if ($rawReqUri != $reqUri) {
            $this->app->request->redirect($reqUri, 301);
        }

        $canUri = $this->uri->output(URI::OUTPUT_DEFINED);

        //safe or extra params
        if ($reqUri != $canUri) {
            $view->setCanonical($canUri);
        }

        $item = $this->uri->getSRC()->getItem();
        $image = $this->app->images->get($item->getImage());

        $params = array_merge($params, $this->getParams());

        $item = $this->uri->getSRC()->getItem();

        if ($category = $this->app->managers->items->getCategory($item)) {
            $categoryName = $category->getName();
        } else {
            $categoryName = 'Item';
        }

        if ($brand = $this->app->managers->items->getBrand($item)) {
            $brandName = $brand->getName();

            if ($brand->isNoIndex()) {
                $this->app->seo->setNoIndexNoFollow($view);
            }
        } else {
            $brandName = 'Awesome';
        }

        $view->setTitle($title = Helper::getNiceSemanticText($this->makeAttrValue('meta_title', $params['meta_title'] ?? null, $params)))
            ->setH1(Helper::getNiceSemanticText($this->makeAttrValue('h1', $params['h1'] ?? $title, $params)))
            ->setHeadPrefix('og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# product: http://ogp.me/ns/product#')
            ->addMetaProperty('fb:app_id', $this->app->config->keys->facebook_app_id(null))
            ->addMetaProperty('og:site_name', $this->app->getSite())
//            ->addMetaProperty('og:locale', strtolower($this->app->trans->getLocale()))
            ->addMetaProperty('og:type', 'product.item')
            ->addMetaProperty('og:url', $canUri)
            ->addMetaProperty('og:title', Helper::getNiceSemanticText($this->makeAttrValue('meta_og_title', $params['meta_og_title'] ?? $title, $params)))
            ->addMetaProperty('og:image', $imageLink = $this->app->images->getLink($image))
            ->addMetaProperty('og:image:secure_url', $imageLink)
            ->addMetaProperty('og:image:type', $this->app->images->getMime($image))
            ->addMetaProperty('og:image:width', $this->app->images->getWidth($image))
            ->addMetaProperty('og:image:height', $this->app->images->getHeight($image))
            ->addMetaProperty('og:image:alt', 'Фото ' . $item->getName())
            ->addMetaProperty('og:image:user_generated', 'false')
            ->addHeadLink('image', $imageLink)
            ->addMetaProperty('product:retailer_item_id', $item->getId())
            ->addMetaProperty('product:price:amount', $item->getOldPrice() > 0 ? $item->getOldPrice(true) : $item->getPrice(true))
            ->addMetaProperty('product:price:currency', $currency = $this->app->config->catalog->currency('RUB'));

        if ($item->getOldPrice() > 0) {
            $view->addMetaProperty('product:sale_price:amount', $item->getPrice(true))
                ->addMetaProperty('product:sale_price:currency', $currency);
        }

        $view
            ->addMetaProperty('product:availability', (!$item->get('archive')) && $item->isInStock() ? 'in stock' : 'out of stock')
            ->addMetaProperty('product:condition', 'new')
            ->addMetaProperty('product:age_group', 'adult')
            ->addMetaProperty('product:brand', $brandName)
            ->addMetaProperty('product:category', $categoryName)
//            ->addMetaProperty('product:color', $this->app->managers->items->getCalors($item))
//            ->addMetaProperty('product:material', $this->app->managers->items->getMaterials($item));
//            ->addMetaProperty('product:size', $this->app->managers->items->getSizes($item))
            ->addMetaProperty('product:target_gender', $this->app->config->catalog->gender('female'))
            ->addMeta('description', $description = Helper::getNiceSemanticText($this->makeAttrValue('meta_description', $params['meta_description'] ?? null, $params)))
            ->addMetaProperty('og:description', Helper::getNiceSemanticText($this->makeAttrValue('meta_og_description', $params['meta_og_description'] ?? $description, $params)))
            ->addMeta('keywords', Helper::getNiceSemanticText($this->makeAttrValue('meta_keywords', $params['meta_keywords'] ?? null, $params)));

        return $this;
    }

    public function manageBreadcrumbs(Layout $view)
    {
        $view->addBreadcrumb($this->app->trans->makeText('catalog.catalog'), (string)new CatalogURI);

        $item = $this->uri->getSRC()->getItem();

        if (!$category = $this->app->managers->items->getCategory($item)) {
            $category = array_values($this->app->managers->categories->getRootObjects())[0];
        }

        foreach ($this->app->managers->categories->getChainedParents($category, true) as $id => $parentCategory) {
            $view->addBreadcrumb(
                $parentCategory->getBreadcrumb() ?: $parentCategory->getName(),
                $this->app->managers->categories->getLink($parentCategory)
            );
        }

        $view->addBreadcrumb($view->getH1());

        return $this;
    }
}