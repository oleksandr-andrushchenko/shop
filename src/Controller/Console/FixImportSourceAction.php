<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_SHOP\App\Console as App;

class FixImportSourceAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$id = $app->request->get('param_1')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        if (!$source = $app->managers->sources->find($id)) {
            throw (new NotFound)->setNonExisting('source');
        }

        $aff = $app->utils->sources->doFixSource($source);

        $app->response->setBody(false === $aff ? 'FAILED' : 'DONE');
    }
}