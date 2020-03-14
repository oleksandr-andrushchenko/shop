<?php

namespace SNOWGIRL_SHOP\Item;

use SNOWGIRL_CORE\AbstractApp;
use SNOWGIRL_CORE\Helper;
use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_CORE\Entity\Page;
use SNOWGIRL_SHOP\Catalog\URI as CatalogURI;

class SEO
{
    /**
     * @var URI
     */
    private $uri;

    private $params;

    /**
     * @var Page
     */
    private $page;

    public function __construct(URI $uri)
    {
        $this->uri = $uri;
    }

    private function getParams(): array
    {
        if (null === $this->params) {
            $item = $this->uri->getSRC()->getItem();

            $this->params = $item->getAttrs();

            $this->params = array_merge($this->params, [
                'site' => $this->uri->getApp()->getSite(),
                'phone' => $this->uri->getApp()->config('site.phone'),
                'category' => $this->uri->getApp()->managers->items->getCategory($item)->getName(),
                'brand' => $brandName = $this->uri->getApp()->managers->items->getBrand($item)->getName(),
//            'color' => ($v = $this->uri->getApp()->managers->items->getColor($this->getItem())) ? $v->getName() : '',
                'partner_item_id' => $item->getPartnerItemId()
            ]);

            $this->params['description'] = implode('. ', [
                $item->getName(),
//            ($v = $this->getItem()->getColor()) ? ('Цвет ' . $v->getName()) : '',
                ($v = $this->uri->getApp()->managers->items->getCountry($item)) ? ('Производитель ' . $v->getName()) : '',
            ]);

            $keywords = [];
            $keywords += explode(' ', $item->getName());
            $keywords += explode(' ', $brandName);
//        $keywords[] = ($v = $this->getItem()->getColor()) ? $v->getName() : '';
            $this->params['keywords'] = implode(',', $keywords);
        }

        return $this->params;
    }

    private function getPage()
    {
        return $this->page ?: $this->page = $this->uri->getApp()->managers->pages->findByKey('item');
    }

    private function makeAttrValue($attr, $default = null, array $params = [])
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
     * @param array $params
     *
     * @return $this|SEO
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function managePage(Layout $view, array $params = [])
    {
        $reqUri = $this->uri->output(URI::OUTPUT_FULL);
        $rawReqUri = $this->uri->getApp()->request->getUri();

        //normalized and de-normalized are different
        if ($rawReqUri != $reqUri) {
            $this->uri->getApp()->request->redirect($reqUri, 301);
        }

        $canUri = $this->uri->output(URI::OUTPUT_DEFINED);

        //safe or extra params
        if ($reqUri != $canUri) {
            $view->setCanonical($canUri);
        }

        $item = $this->uri->getSRC()->getItem();
        $image = $this->uri->getApp()->images->get($item->getImage());

        $params = array_merge($params, $this->getParams());

        $item = $this->uri->getSRC()->getItem();

        $view->setTitle($title = Helper::getNiceSemanticText($this->makeAttrValue('meta_title', $params['meta_title'] ?? null, $params)))
            ->setH1(Helper::getNiceSemanticText($this->makeAttrValue('h1', $params['h1'] ?? $title, $params)))
            ->setHeadPrefix('og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# product: http://ogp.me/ns/product#')
            ->addMetaProperty('fb:app_id', $this->uri->getApp()->config('keys.facebook_app_id'))
            ->addMetaProperty('og:site_name', $this->uri->getApp()->getSite())
//            ->addMetaProperty('og:locale', strtolower($this->uri->getApp()->trans->getLocale()))
            ->addMetaProperty('og:type', 'product.item')
            ->addMetaProperty('og:url', $canUri)
            ->addMetaProperty('og:title', Helper::getNiceSemanticText($this->makeAttrValue('meta_og_title', $params['meta_og_title'] ?? $title, $params)))
            ->addMetaProperty('og:image', $imageLink = $this->uri->getApp()->images->getLink($image))
            ->addMetaProperty('og:image:secure_url', $imageLink)
            ->addMetaProperty('og:image:type', $this->uri->getApp()->images->getMime($image))
            ->addMetaProperty('og:image:width', $this->uri->getApp()->images->getWidth($image))
            ->addMetaProperty('og:image:height', $this->uri->getApp()->images->getHeight($image))
            ->addMetaProperty('og:image:alt', 'Фото ' . $item->getName())
            ->addMetaProperty('og:image:user_generated', 'false')
            ->addHeadLink('image', $imageLink)
            ->addMetaProperty('product:retailer_item_id', $item->getId())
            ->addMetaProperty('product:price:amount', $item->getOldPrice() > 0 ? $item->getOldPrice(true) : $item->getPrice(true))
            ->addMetaProperty('product:price:currency', $currency = $this->uri->getApp()->config('catalog.currency', 'RUB'));

        if ($item->getOldPrice() > 0) {
            $view->addMetaProperty('product:sale_price:amount', $item->getPrice(true))
                ->addMetaProperty('product:sale_price:currency', $currency);
        }

        $view
            ->addMetaProperty('product:availability', (!$item->get('archive')) && $item->isInStock() ? 'in stock' : 'out of stock')
            ->addMetaProperty('product:condition', 'new')
            ->addMetaProperty('product:age_group', 'adult')
            ->addMetaProperty('product:brand', $this->uri->getApp()->managers->items->getBrand($item)->getName())
            ->addMetaProperty('product:category', $this->uri->getApp()->managers->items->getCategory($item)->getName())
//            ->addMetaProperty('product:color', $this->uri->getApp()->managers->items->getCalors($item))
//            ->addMetaProperty('product:material', $this->uri->getApp()->managers->items->getMaterials($item));
//            ->addMetaProperty('product:size', $this->uri->getApp()->managers->items->getSizes($item))
            ->addMetaProperty('product:target_gender', $this->uri->getApp()->config('catalog.gender', 'female'))
            ->addMeta('description', $description = Helper::getNiceSemanticText($this->makeAttrValue('meta_description', $params['meta_description'] ?? null, $params)))
            ->addMetaProperty('og:description', Helper::getNiceSemanticText($this->makeAttrValue('meta_og_description', $params['meta_og_description'] ?? $description, $params)))
            ->addMeta('keywords', Helper::getNiceSemanticText($this->makeAttrValue('meta_keywords', $params['meta_keywords'] ?? null, $params)));

        return $this;
    }

    public function manageBreadcrumbs(Layout $view)
    {
        $view->addBreadcrumb($this->uri->getApp()->trans->makeText('catalog.catalog'), (string)new CatalogURI);

        $item = $this->uri->getSRC()->getItem();
        $category = $this->uri->getApp()->managers->items->getCategory($item);

        foreach ($this->uri->getApp()->managers->categories->getChainedParents($category, true) as $id => $parentCategory) {
            $view->addBreadcrumb(
                $parentCategory->getBreadcrumb() ?: $parentCategory->getName(),
                $this->uri->getApp()->managers->categories->getLink($parentCategory)
            );
        }

        $view->addBreadcrumb($view->getH1());

        return $this;
    }
}