<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 9/25/18
 * Time: 11:28 PM
 */

namespace SNOWGIRL_SHOP\Manager\Page\Catalog;

use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Entity\User;

/**
 * Class Custom
 * @package SNOWGIRL_SHOP\Manager\Page\Catalog
 */
class Custom extends Manager
{
    protected $masterServices = false;

    /**
     * @param $user
     * @return User
     */
    protected function normalizeUser($user)
    {
        if (is_numeric($user)) {
            $user = $this->app->managers->users->find($user);
        } elseif (!$user instanceof User) {
            throw new Exception(__METHOD__ . ': $user должен быть обьектом или числом');
        }

        return $user;
    }

    public function isCanActiveSeoTexts($user)
    {
        $user = $this->normalizeUser($user);

        if ($user->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            return true;
        }

        return false;
    }

    public function isCanEditSeoText(array $text, $user)
    {
        $user = $this->normalizeUser($user);

        if ($user->isRole(User::ROLE_ADMIN)) {
            return true;
        }

        if ($user->isRole(User::ROLE_COPYWRITER) && $text['user'] && $text['user'] === $user->getId()) {
            return true;
        }

        return false;
    }

    public function isCanDeleteSeoText(array $text, $user)
    {
        $user = $this->normalizeUser($user);

        if ($user->isRole(User::ROLE_ADMIN)) {
            return true;
        }



        if ($user->isRole(User::ROLE_COPYWRITER) && $text['user'] && $text['user'] === $user->getId()) {
            return true;
        }

        return false;
    }

    public function isCanModifyAttrs($user)
    {
        $user = $this->normalizeUser($user);

        if ($user->isRole(User::ROLE_ADMIN)) {
            return true;
        }

        return false;
    }
}