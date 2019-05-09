<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 7/23/17
 * Time: 7:57 PM
 */
namespace SNOWGIRL_SHOP\View\Layout;

use SNOWGIRL_CORE\Entity\User;
use SNOWGIRL_CORE\Script\Css;
use SNOWGIRL_CORE\Script\Js;

/**
 * Class Admin
 * @package SNOWGIRL_SHOP\View\Layout
 */
class Admin extends \SNOWGIRL_CORE\View\Layout\Admin
{
    protected function addCssNodes()
    {
        return parent::addCssNodes()
            ->addHeadCss(new Css('@snowgirl-shop/core.css'));
    }

    protected function addJsNodes()
    {
        return parent::addJsNodes()
            ->addJs(new Js('@snowgirl-shop/core.js'))
            ->addJs(new Js('@snowgirl-shop/admin/core.js'));
    }

    protected function addMenuNodes()
    {
        parent::addMenuNodes();

//        $this->addMenu('Главная', $this->makeLink('index'));

        if ($this->client->isLoggedIn()) {
            if ($this->client->getUser()->isRole(User::ROLE_ADMIN)) {
                $this->addMenu('БД', $this->makeLink('admin', 'database'));
            }

            if ($this->client->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
                $this->addMenu('Категории', $this->makeLink('admin', 'categories'));
            }

            if ($this->client->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_COPYWRITER)) {
                $this->addMenu('Каталог', $this->makeLink('admin', 'catalog'));
            }

            if ($this->client->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
                $this->addMenu('Офферы', $this->makeLink('admin', 'offers'))
                    ->addMenu('Фиксы', $this->makeLink('admin', 'item-fixes'))
                    ->addMenu('Разное', $this->makeLink('admin', 'control'));
            }

//            if ($this->client->getUser()->isRole(User::ROLE_ADMIN)) {
//                $this->addMenu('Профайлер', $this->makeLink('admin', 'profiler'));
//            }
        }

        return $this;
    }
}