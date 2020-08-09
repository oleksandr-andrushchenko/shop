<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class DeleteItemsWithBadAttrsAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $aff = $app->utils->items->doDeleteWithNonExistingCategories();

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            is_int($aff) ? "DONE[non-existing-categories]={$aff}" : 'FAILED',
        ]));

        $aff = $app->utils->items->doDeleteWithNonExistingBrands();

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            is_int($aff) ? "DONE[non-existing-brands]={$aff}" : 'FAILED',
        ]));
    }
}