<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 9:52 PM
 */

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\App\Web as App;
use SNOWGIRL_CORE\Entity\User;

class IndexAction extends \SNOWGIRL_CORE\Controller\Admin\IndexAction
{
    protected function getDefaultAction(App $app): string
    {
        if ($app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            $action = 'offers';
        } elseif ($app->request->getClient()->getUser()->isRole(User::ROLE_COPYWRITER)) {
            $action = 'catalog';
        } else {
            $action = 'logout';
        }

        return $action;
    }
}