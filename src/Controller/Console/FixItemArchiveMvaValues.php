<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class FixItemArchiveMvaValues
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $aff = $app->utils->items->doFixArchiveMvaValues();
        $app->response->setBody($aff ? "DONE: {$aff}" : 'FAILED');
    }
}