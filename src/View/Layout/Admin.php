<?php

namespace SNOWGIRL_SHOP\View\Layout;

use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_SHOP\RBAC;

class Admin extends \SNOWGIRL_CORE\View\Layout\Admin
{
    protected function addCssNodes(): Layout
    {
        return parent::addCssNodes()
            ->addHeadCss('@shop/core.css');
    }

    protected function addJsNodes(): Layout
    {
        return parent::addJsNodes()
            ->addJs('@shop/core.js')
            ->addJs('@shop/admin/core.js');
    }

    protected function addMenuNodes(): Layout
    {
        parent::addMenuNodes();

        $tmp = [];
        $tmp[] = $this->app->rbac->hasPerm(RBAC::PERM_DATABASE_PAGE) ? 'database' : false;
        $tmp[] = $this->app->rbac->hasPerm(RBAC::PERM_CATEGORIES_PAGE) ? 'categories' : false;
        $tmp[] = $this->app->rbac->hasPerm(RBAC::PERM_CATALOG_PAGE) ? 'catalog' : false;
        $tmp[] = $this->app->rbac->hasPerm(RBAC::PERM_OFFERS_PAGE) ? 'offers' : false;
        $tmp[] = $this->app->rbac->hasPerm(RBAC::PERM_CATEGORY_FIXES_PAGE) ? 'category-fixes' : false;
        $tmp[] = $this->app->rbac->hasPerm(RBAC::PERM_CONTROL_PAGE) ? 'control' : false;
//        $tmp[] = $this->app->rbac->hasPerm(RBAC::PERM_PROFILER_PAGE) ? 'profiler' : false;

        $tmp = array_filter($tmp, function ($uri) {
            return false !== $uri;
        });

        foreach ($tmp as $action) {
            $this->addMenu($this->makeText('layout.admin.' . $action), $this->makeLink('admin', $action));
        }

        return $this;
    }
}