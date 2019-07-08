<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Console as App;

class ItemsInMongoTransferAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     *
     * @throws \Exception
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $aff = $app->utils->items->doInMongoTransfer();

        $app->response->setBody(is_int($aff) ? "DONE: {$aff}" : 'FAILED');
    }
}