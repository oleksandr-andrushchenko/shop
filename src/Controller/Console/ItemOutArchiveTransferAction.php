<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class ItemOutArchiveTransferAction
{
    use PrepareServicesTrait;
    use GetSimpleItemsWhereTrait;

    /**
     * @param App $app
     * @throws \SNOWGIRL_CORE\Http\Exception\NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $where = $this->getSimpleItemsWhere($app);

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            $app->utils->items->doOutArchiveTransfer($where) ? 'DONE' : 'FAILED',
        ]));
    }
}