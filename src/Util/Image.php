<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_CORE\App;
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
        $db = $this->app->services->rdbms;
        $itemTable = $this->app->managers->items->getEntity()->getTable();

        $query = implode(' ', [
            'SELECT',
            $db->quote('image'),
            'FROM',
            $itemTable,
            'WHERE',
            $tmp = 'DATE(' . $db->quote('created_at') . ') > \'2016-09-05\''
        ]);

        foreach ($db->req($query)->reqToArrays() as $item) {
            (new ImageObject($item['image']))->delete();
            $this->output($item['image'] . ' deleted');
        }

        return $db->deleteMany($itemTable, new Expr($tmp));
    }
}