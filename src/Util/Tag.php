<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Util;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Http\HttpApp;

/**
 * @property HttpApp|ConsoleApp app
 */
class Tag extends Util
{
    public function doClearNonLeafCategoriesItemsTags()
    {
        $this->output('::actionSyncIsLeafCategories');
        $output = $this->app->managers->categories->syncTree();
        $this->output($output ? 'DONE' : 'FAILED');

        $manager = $this->app->managers->categories->clear();

        if (!$nonLeafsIds = array_diff($manager->getIds(), $manager->getLeafsIds())) {
            $this->output('DONE[there are no non leafs categories ids]');
        }

        $mysql = $this->app->container->mysql;

        $aff = $mysql->req(implode(' ', [
            'DELETE ' . ' ' . $mysql->quote('item_tag'),
            'FROM ' . $mysql->quote('item_tag'),
            'INNER JOIN ' . $mysql->quote('item') . ' USING(' . $mysql->quote('item_id') . ')',
            'WHERE ' . $mysql->quote('category_id') . ' IN (' . implode(',', $nonLeafsIds) . ')'
        ]))->affectedRows();

        $this->output('DONE[aff=' . $aff . ']');

        return true;
    }
}