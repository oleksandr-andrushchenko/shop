<?php

namespace SNOWGIRL_SHOP;

use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_CORE\View\Widget\Pager;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Item\URI as ItemURI;
use SNOWGIRL_SHOP\SEO\Pages;
use SNOWGIRL_SHOP\SEO\RobotsTxt;
use SNOWGIRL_SHOP\SEO\Sitemap;

/**
 * Class SEO
 *
 * @property App app
 * @method App getApp()
 * @method Pages getPages()
 * @method RobotsTxt getRobotsTxt()
 * @method Sitemap getSitemap()
 * @package SNOWGIRL_SHOP
 */
class SEO extends \SNOWGIRL_CORE\SEO
{
    public function manageCatalogPage(URI $uri, Layout $view, array $params = [])
    {
        $uri->getSEO()->managePage($view, $params);
    }

    public function manageCatalogPager(URI $uri, Pager $pager, Layout $view)
    {
        $uri->getSEO()->managePager($pager, $view);
    }

    public function manageCatalogBreadcrumbs(URI $uri, $h1ParamsSize, Layout $view)
    {
        $uri->getSEO()->manageBreadcrumbs($h1ParamsSize, $view);
    }

    public function manageItemPage(ItemURI $uri, Layout $view, array $params = [])
    {
        $uri->getSEO()->managePage($view, $params);
    }

    public function manageItemBreadcrumbs(ItemURI $uri, Layout $view)
    {
        $uri->getSEO()->manageBreadcrumbs($view);
    }
}