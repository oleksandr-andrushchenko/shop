<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\App\Web as App;
use SNOWGIRL_SHOP\RBAC;

class IndexAction extends \SNOWGIRL_CORE\Controller\Admin\IndexAction
{
    protected function getDefaultAction(App $app): string
    {
        if ($app->rbac->hasPerm(RBAC::PERM_OFFERS_PAGE)) {
            $action = 'offers';
        } elseif ($app->rbac->hasPerm(RBAC::PERM_CATALOG_PAGE)) {
            $action = 'catalog';
        } else {
            $action = 'logout';
        }

        return $action;
    }
}