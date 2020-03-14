<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_CORE\AbstractApp;
use SNOWGIRL_CORE\Image as ImageObject;

/**
 * Class Image
 *
 * @property App app
 * @package SNOWGIRL_SHOP\Util
 */
class Image extends \SNOWGIRL_CORE\Util\Image
{
    public function doDeleteBadQuality()
    {
        $db = $this->app->container->db;
        $itemTable = $this->app->managers->items->getEntity()->getTable();

        $query = implode(' ', [
            'SELECT',
            $db->quote('image'),
            'FROM',
            $itemTable,
            'WHERE',
            $tmp = 'DATE(' . $db->quote('created_at') . ') > \'2016-09-05\''
        ]);

        foreach ($db->reqToArrays($query) as $item) {
            (new ImageObject($item['image']))->delete();
            $this->output($item['image'] . ' deleted');
        }

        return $db->deleteMany($itemTable, new Expression($tmp));
    }
}