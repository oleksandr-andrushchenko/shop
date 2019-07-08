<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Console as App;

class AddItemsArchiveImportSourceIdAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->response->setBody(implode("\r\n", [
            __CLASS__,
            $app->utils->items->doAddArchiveImportSourceId() ? 'DONE' : 'FAILED'
        ]));
    }
}