<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class DeleteItemsNonExistingMvaAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->response->setBody(implode("\r\n", [
            __CLASS__,
            $app->utils->attrs->doDeleteNonExistingItemsMva() ? 'DONE' : 'FAILED'
        ]));
    }
}