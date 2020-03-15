<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class AttrsInMongoTransferAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $attrs = ($tmp = $app->request->get('param_1')) ? explode(',', $tmp) : [];
        $aff = $app->utils->attrs->doInMongoTransfer($attrs);

        $app->response->setBody(is_int($aff) ? "DONE: {$aff}" : 'FAILED');
    }
}