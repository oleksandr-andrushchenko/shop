<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 11:52 PM
 */

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_SHOP\App\Console as App;

trait GetSimpleItemsWhereTrait
{
    protected function getSimpleItemsWhere(App $app)
    {
        if (!$whereKey = $app->request->get('param_1')) {
            throw (new BadRequest)->setInvalidParam('where_key');
        }

        if (!$app->managers->items->getEntity()->hasAttr($whereKey)) {
            throw (new NotFound)->setNonExisting('where_key');
        }

        if (!$app->request->has('param_2')) {
            throw (new BadRequest)->setInvalidParam('where_value');
        }

        $whereValue = $app->request->get('param_2');

        return [$whereKey => $whereValue];
    }
}