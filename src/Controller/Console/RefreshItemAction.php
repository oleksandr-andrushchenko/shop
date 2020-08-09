<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use Elasticsearch\Common\Exceptions\Missing404Exception;
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

        if (!$itemId = $app->request->get('param_1')) {
            throw (new BadRequestHttpException)->setInvalidParam('item_id');
        }

        if (!$item = $app->managers->items->find($itemId)) {
            throw (new NotFoundHttpException)->setNonExisting('item');
        }

        $response = [];

        $response['Is in stock'] = ($item->isInStock() ? 'true' : 'false');

        $realIsInStock = $app->managers->items->checkRealIsInStock($item);
        $response['Real is in stock'] = var_export($realIsInStock, true);

        if (is_bool($realIsInStock)) {
            $item->setIsInStock($realIsInStock);
            $aff = $app->managers->items->updateOne($item);
            $response['Update db response'] = var_export($aff, true);
        }

        try {
            // @todo create elastic upsert method
            $aff = $app->managers->items->addToIndex($item, true);
        } catch (Missing404Exception $e) {
            $aff = $app->managers->items->addToIndex($item);
        }

        $response['Update index response'] = var_export($aff, true);

        $aff = $app->managers->items->deleteCache($item);
        $response['Delete cache response'] = var_export($aff, true);

        $app->response->addToBody(implode("\r\n", array_merge($this->formatResponse($response), [
            __CLASS__,
            'DONE',
        ])));
    }

    private function formatResponse(array $response): array
    {
        $max = 0;

        array_walk($response, function ($v,$k) use (&$max) {
            $max = max($max, mb_strlen($k));
        });

        array_walk($response, function (&$v, &$k) use($max) {
            $v = str_pad($k, $max) . ': ' . $v;
        });

        return $response;
    }
}