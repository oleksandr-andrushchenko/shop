<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Exception;
use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\MethodNotAllowedHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Http\HttpApp as App;

class CheckItemIsInStockAction
{
    use PrepareServicesTrait;

    const CACHE_KEY_CHECKED = 'item-is-in-stock-checked-%id%';
    const CACHE_LIFETIME_CHECKED = 3600;

    /**
     * @param App $app
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$app->request->isGet()) {
            throw (new MethodNotAllowedHttpException)->setValidMethod('get');
        }

        if (!$id = $app->request->get('id')) {
            throw (new BadRequestHttpException)->setInvalidParam('id');
        }

        if (!$item = $app->managers->items->find($id)) {
            throw (new NotFoundHttpException)->setNonExisting('item');
        }

        if ($item->isInStock()) {
            $checkedCacheKey = $this->getCheckedCacheKey($item);
            $app->container->cache->has($checkedCacheKey, $checkedCacheValue);

            if ($checkedCacheValue) {
                $answer = true;
                $app->container->logger->info('Is in stock item already checked', [
                    'item_id' => $item->getId(),
                ]);
            } else {
                $answer = $app->managers->items->checkRealIsInStock($item);

                if (null === $answer) {
                    $app->container->logger->warning('Unknown item is in stock status', [
                        'item_id' => $item->getId(),
                    ]);

//                    if ($vendor = $app->managers->items->getVendor($item)) {
//                        $vendor->setIsInStockCheck(false);
//                        $app->managers->vendors->updateOne($vendor);
//                    }
                } elseif (false === $answer) {
                    $item->setIsInStock(false);
//                    $item->setOrders(9999999);

                    if ($app->managers->items->updateOne($item)) {
                        try {
                            // @todo create elastic upsert method
                            if ($app->configMasterOrOwn('catalog.in_stock_only', false)) {
                                $app->managers->items->deleteFromIndex($item);
                            } else {
                                $app->managers->items->addToIndex($item);
                            }
                        } catch (Missing404Exception $e) {
                            $app->container->logger->error($e);
                        }
                    }

                    $app->container->logger->warning('Item marked as out of stock', [
                        'item_id' => $item->getId(),
                    ]);
                } else {
                    $app->container->cache->set($checkedCacheKey, true, self::CACHE_LIFETIME_CHECKED);
                    $app->container->logger->info('Confirmed is in stock item status', [
                        'item_id' => $item->getId(),
                    ]);
                }
            }
        } else {
            $answer = false;
            $app->container->logger->warning('Out of stock item request received', [
                'item_id' => $id,
            ]);
        }

        $app->response->setJSON(200, [
            'in_stock' => $answer,
        ]);
    }

    private function getCheckedCacheKey(Item $item): string
    {
        return str_replace('%id%', $item->getId(), self::CACHE_KEY_CHECKED);
    }
}