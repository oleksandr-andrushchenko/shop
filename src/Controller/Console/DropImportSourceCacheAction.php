<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_SHOP\App\Console as App;

class DropImportSourceCacheAction
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

        $aff = $app->managers->sources->getImport($source)->dropCache();

        $app->response->setBody('AFF: ' . var_export($aff, true));
    }
}