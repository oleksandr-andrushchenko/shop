<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Mysql\MysqlQueryExpression;
use SNOWGIRL_CORE\Image as ImageObject;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Http\HttpApp;

/**
 * @property HttpApp|ConsoleApp app
 */
class Image extends \SNOWGIRL_CORE\Util\Image
{
    public function doDeleteBadQuality()
    {
        $mysql = $this->app->container->mysql;
        $itemTable = $this->app->managers->items->getEntity()->getTable();

        $query = implode(' ', [
            'SELECT',
            $mysql->quote('image'),
            'FROM',
            $itemTable,
            'WHERE',
            $tmp = 'DATE(' . $mysql->quote('created_at') . ') > \'2016-09-05\''
        ]);

        foreach ($mysql->reqToArrays($query) as $item) {
            (new ImageObject($item['image']))->delete();
            $this->output($item['image'] . ' deleted');
        }

        return $mysql->deleteMany($itemTable, new MysqlQueryExpression($tmp));
    }
}