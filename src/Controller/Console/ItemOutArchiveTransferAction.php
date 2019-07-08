<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Console as App;

class ItemOutArchiveTransferAction
{
    use PrepareServicesTrait;
    use GetSimpleItemsWhereTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $where = $this->getSimpleItemsWhere($app);

        $app->response->setBody(implode("\r\n", [
            __CLASS__,
            $app->utils->items->doOutArchiveTransfer($where) ? 'DONE' : 'FAILED'
        ]));
    }
}