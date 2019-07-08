<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Console as App;

class FixItemsWithNonExistingAttrsAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $aff = $app->utils->items->doFixWithNonExistingAttrs();
        $app->response->setBody(is_int($aff) ? "DONE: {$aff}" : 'FAILED');
    }
}