<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Entity\Banner;
use SNOWGIRL_CORE\Entity\Contact;
use SNOWGIRL_CORE\Entity\Subscribe;
use SNOWGIRL_CORE\Entity\User;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_CORE\App;
use SNOWGIRL_SHOP\Entity\Stock;
use SNOWGIRL_CORE\Entity\Page\Regular as PageRegular;

/**
 * Class Site
 *
 * @property App app
 * @package SNOWGIRL_SHOP\Util
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
        $db = $this->app->services->rdbms;

        $column = 'site_id';
        $options = 'tinyint(1) NOT NULL DEFAULT \'0\'';

        $db->addTableColumn(Banner::getTable(), $column, $options . ' after ' . $db->quote('is_active'));
        $db->addTableColumn(Contact::getTable(), $column, $options . ' after ' . $db->quote('body'));
        $db->addTableColumn(Stock::getTable(), $column, $options . ' after ' . $db->quote('is_active'));
        $db->addTableColumn(Subscribe::getTable(), $column, $options . ' after ' . $db->quote('is_active'));
        $db->dropTableKey(Subscribe::getTable(), 'email');
        $db->addTableKey(Subscribe::getTable(), 'email', ['site_id', 'email'], true);
        $db->dropTableKey(Subscribe::getTable(), 'code');
        $db->addTableKey(Subscribe::getTable(), 'code', ['site_id', 'code'], true);
        $db->addTableColumn(User::getTable(), $column, $options . ' after ' . $db->quote('role'));
        $db->dropTableKey(User::getTable(), 'login');
        $db->addTableKey(User::getTable(), 'login', ['site_id', 'login'], true);
        $db->addTableColumn(PageRegular::getTable(), $column, $options . ' after ' . $db->quote('is_menu'));
        $db->dropTableKey(User::getTable(), 'key');
        $db->addTableKey(User::getTable(), 'key', ['site_id', 'key'], true);

        return true;
    }

    /**
     * @todo...
     */
    public function doCreateDatabaseFromAnother()
    {

    }
}