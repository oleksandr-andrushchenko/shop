<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Controller\Admin\ExecTrait;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class ImportSourceSaveFilterAction
{
    use PrepareServicesTrait;
    use ExecTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_SAVE_IMPORT_SOURCE_FILTER);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        $source = $app->managers->sources->find($id);

        self::_exec($app, 'Фильтры файла поставщика успешно обновлены', function (App $app) use ($source) {
            $app->managers->sources->updateFileFilter($source, $app->request->get('filter', []));
        });

        $app->request->redirect($app->request->getReferer());
    }
}