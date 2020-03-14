<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_CORE\AbstractApp;
use SNOWGIRL_SHOP\Manager\Tag as TagManager;
use SNOWGIRL_SHOP\Entity\Tag as TagEntity;
use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Entity\Brand;
use SNOWGIRL_SHOP\Entity\Item;

/**
 * Class Tag
 *
 * @property App app
 * @package SNOWGIRL_SHOP\Util
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

        $db = $this->app->container->db;

        $aff = $db->req(implode(' ', [
            'DELETE ' . ' ' . $db->quote('item_tag'),
            'FROM ' . $db->quote('item_tag'),
            'INNER JOIN ' . $db->quote('item') . ' USING(' . $db->quote('item_id') . ')',
            'WHERE ' . $db->quote('category_id') . ' IN (' . implode(',', $nonLeafsIds) . ')'
        ]))->affectedRows();

        $this->output('DONE[aff=' . $aff . ']');

        return true;
    }
}