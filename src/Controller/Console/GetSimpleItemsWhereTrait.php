<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

trait GetSimpleItemsWhereTrait
{
    protected function getSimpleItemsWhere(App $app)
    {
        if (!$whereKey = $app->request->get('param_1')) {
            throw (new BadRequestHttpException)->setInvalidParam('where_key');
        }

        if (!$app->managers->items->getEntity()->hasAttr($whereKey)) {
            throw (new NotFoundHttpException)->setNonExisting('where_key');
        }

        if (!$app->request->has('param_2')) {
            throw (new BadRequestHttpException)->setInvalidParam('where_value');
        }

        $whereValue = $app->request->get('param_2');

        return [$whereKey => $whereValue];
    }
}