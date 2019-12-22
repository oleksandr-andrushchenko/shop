<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Console as App;

class DeleteItemsWithBadAttrsAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $aff = $app->utils->items->doDeleteWithNonExistingCategories();
        $app->response->addToBody(is_int($aff) ? "DONE[non-existing-categories]={$aff}" : 'FAILED');

        $app->response->addToBody("\n");

        $aff = $app->utils->items->doDeleteWithNonExistingBrands();
        $app->response->addToBody(is_int($aff) ? "DONE[non-existing-brands]={$aff}" : 'FAILED');
    }
}