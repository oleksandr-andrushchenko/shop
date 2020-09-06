<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class UpdatePagesAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $index = 1 == $app->request->get('param_1', 0);

        $aff = $app->utils->catalog->doGenerate();

        if ($index) {
            $app->utils->catalog->doIndexIndexer();
        }

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            "DONE: {$aff}",
        ]));
    }
}