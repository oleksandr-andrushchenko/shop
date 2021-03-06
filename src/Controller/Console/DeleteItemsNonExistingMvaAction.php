<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class DeleteItemsNonExistingMvaAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws \SNOWGIRL_CORE\Http\Exception\NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $aff = $app->utils->attrs->doDeleteNonExistingItemsMva();

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            $aff ? "DONE: {$aff}" : 'FAILED',
        ]));
    }
}