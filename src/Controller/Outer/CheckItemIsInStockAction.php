<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Exception\HTTP\MethodNotAllowed;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Http\HttpApp as App;

class CheckItemIsInStockAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$app->request->isGet()) {
            throw (new MethodNotAllowed)->setValidMethod('get');
        }

        if (!$id = $app->request->get('id')) {
            throw (new BadRequestHttpException)->setInvalidParam('id');
        }

        if (!$item = $app->managers->items->find($id)) {
            throw (new NotFoundHttpException)->setNonExisting('item');
        }

        if ($item->isInStock()) {
            $answer = true;
        } else {
            $answer = $app->managers->items->checkRealIsInStock($item);

            if (true === $answer) {
                $item->setIsInStock(true);
                $app->managers->items->updateOne($item);

                $app->container->cache->set(
                    $app->managers->items->getCacheKeyByEntity($item),
                    $item->getAttrs()
                );
            }
        }

        $app->response->setJSON(200, [
//            'in_stock' => true,
//            'in_stock' => false,
//            'in_stock' => null,
            'in_stock' => $answer
        ]);
    }
}