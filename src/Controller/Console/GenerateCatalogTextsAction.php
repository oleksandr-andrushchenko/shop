<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class GenerateCatalogTextsAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $aff = $app->utils->catalog->doGenerateTexts();
        $app->response->setBody(is_int($aff) ? "DONE: {$aff}" : 'FAILED');
    }
}