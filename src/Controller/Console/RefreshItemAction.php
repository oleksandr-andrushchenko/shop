<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class RefreshItemAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$itemId = $app->request->has('param_1')) {
            throw (new BadRequestHttpException)->setInvalidParam('item_id');
        }

        if (!$item = $app->managers->items->find($itemId)) {
            throw (new NotFoundHttpException)->setNonExisting('item');
        }

        $response = [];

        $response[] = 'Is in stock: ' . ($item->isInStock() ? 'true' : 'false');

        $realIsInStock = $app->managers->items->checkRealIsInStock($item);
        $response[] = 'Real is in stock: ' . var_export($realIsInStock, true);

        if (is_bool($realIsInStock)) {
            $item->setIsInStock($realIsInStock);
            $aff = $app->managers->items->updateOne($item);
            $response[] = 'Update db response: ' . var_export($aff, true);
        }

        $aff = $app->managers->items->addToIndex($item, true);
        $response[] = 'Update index response: ' . var_export($aff, true);

        $aff = $app->managers->items->deleteCache($item);
        $response[] = 'Delete cache response: ' . var_export($aff, true);

        $response[] = __CLASS__;
        $response[] = 'DONE';

        $app->response->addToBody(implode("\r\n", $response));
    }
}