<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class GenerateCatalogTextsAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws \SNOWGIRL_CORE\Http\Exception\NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $aff = $app->utils->catalog->doGenerateTexts();

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            is_int($aff) ? "DONE: {$aff}" : 'FAILED',
        ]));
    }
}