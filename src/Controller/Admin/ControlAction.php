<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 9:55 PM
 */

namespace SNOWGIRL_SHOP\Controller\Admin;

class ControlAction extends \SNOWGIRL_CORE\Controller\Admin\ControlAction
{
    protected function getButtons(): array
    {
        return [
            [
                'text' => 'Страницы + Sitemap',
                'icon' => 'refresh',
                'class' => 'success',
                'action' => 'generate-pages-and-sitemap'
            ],
            [
                'text' => 'Sitemap',
                'icon' => 'refresh',
                'class' => 'info',
                'action' => 'generate-sitemap'
            ],
            [
                'text' => 'Rotate Cache',
                'icon' => 'refresh',
                'class' => 'warning',
                'action' => 'rotate-cache'
            ],
            [
                'text' => 'Rotate Sphinx',
                'icon' => 'refresh',
                'class' => 'default',
                'action' => 'rotate-sphinx'
            ],
        ];
    }
}