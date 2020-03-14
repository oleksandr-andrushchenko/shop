<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Controller\Admin\ExecTrait;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class ImportSourceDeleteDuplicateItemsAction
{
    use PrepareServicesTrait;
    use ExecTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_ALL);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequestHttpException)->setInvalidParam('id');
        }

        self::_exec($app, 'Дубликаты предложений успешно удалены', function (App $app) use ($id) {
            $source = $app->managers->sources->find($id);
            $aff = $app->utils->import->doDeleteImportSourceItemsDuplicates($source);
            return 'Удалено: ' . $aff;
        }, true);
    }
}