<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Console as App;

class FixItemArchiveMvaValues
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->response->setBody(($aff = $app->utils->items->doFixArchiveMvaValues()) ? "DONE: {$aff}" : 'FAILED');
    }
}