<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Http\HttpApp as App;
use SNOWGIRL_SHOP\RBAC;

class ControlAction extends \SNOWGIRL_CORE\Controller\Admin\ControlAction
{
    protected function getButtons(App $app): array
    {
        $tmp = parent::getButtons($app);

        if ($app->rbac->hasPerm(RBAC::PERM_GENERATE_PAGES)) {
            array_unshift($tmp, [
                'text' => 'Страницы + Sitemap',
                'icon' => 'refresh',
                'class' => 'success',
                'action' => 'generate-pages-and-sitemap'
            ]);
        }


        return $tmp;
    }
}