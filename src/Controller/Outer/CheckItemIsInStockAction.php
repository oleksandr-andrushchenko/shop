<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\MethodNotAllowed;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_SHOP\App\Web as App;

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
            throw (new BadRequest)->setInvalidParam('id');
        }

        if (!$item = $app->managers->items->find($id)) {
            throw (new NotFound)->setNonExisting('item');
        }

        if ($item->isInStock()) {
            $answer = true;
        } else {
            $answer = $app->managers->items->checkRealIsInStock($item);

            if (true === $answer) {
                $item->setIsInStock(true);
                $app->managers->items->updateOne($item);

                if ($app->services->mcms->isOn()) {
                    //do manual cache coz of ftdbms storage
                    $app->services->mcms->set(
                        $app->managers->items->getCacheKeyByEntity($item),
                        $item->getAttrs()
                    );
                }
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