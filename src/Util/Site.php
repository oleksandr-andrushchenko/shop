<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Entity\Banner;
use SNOWGIRL_CORE\Entity\Contact;
use SNOWGIRL_CORE\Entity\Subscribe;
use SNOWGIRL_CORE\Entity\User;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Entity\Stock;
use SNOWGIRL_SHOP\Http\HttpApp;

/**
 * @property HttpApp|ConsoleApp app
 */
class Site extends Util
{
    /**
     * @todo site-id...
     *
     * @param $siteId
     *
     * @return bool
     */
    public function doAddTablesIdColumns($siteId)
    {
        $mysql = $this->app->container->mysql;

        $column = 'site_id';
        $options = 'tinyint(1) NOT NULL DEFAULT \'0\'';

        $mysql->addTableColumn(Banner::getTable(), $column, $options . ' after ' . $mysql->quote('is_active'));
        $mysql->addTableColumn(Contact::getTable(), $column, $options . ' after ' . $mysql->quote('body'));
        $mysql->addTableColumn(Stock::getTable(), $column, $options . ' after ' . $mysql->quote('is_active'));
        $mysql->addTableColumn(Subscribe::getTable(), $column, $options . ' after ' . $mysql->quote('is_active'));
        $mysql->dropTableKey(Subscribe::getTable(), 'email');
        $mysql->addTableKey(Subscribe::getTable(), 'email', ['site_id', 'email'], true);
        $mysql->dropTableKey(Subscribe::getTable(), 'code');
        $mysql->addTableKey(Subscribe::getTable(), 'code', ['site_id', 'code'], true);
        $mysql->addTableColumn(User::getTable(), $column, $options . ' after ' . $mysql->quote('role'));
        $mysql->dropTableKey(User::getTable(), 'login');
        $mysql->addTableKey(User::getTable(), 'login', ['site_id', 'login'], true);
        $mysql->addTableColumn(PageRegular::getTable(), $column, $options . ' after ' . $mysql->quote('is_menu'));
        $mysql->dropTableKey(User::getTable(), 'key');
        $mysql->addTableKey(User::getTable(), 'key', ['site_id', 'key'], true);

        return true;
    }

    /**
     * @todo...
     */
    public function doCreateDatabaseFromAnother()
    {

    }
}