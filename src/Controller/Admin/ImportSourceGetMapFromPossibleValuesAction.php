<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class ImportSourceGetMapFromPossibleValuesAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_ALL);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequestHttpException)->setInvalidParam('id');
        }

        if (!$column = $app->request->get('column')) {
            throw (new BadRequestHttpException)->setInvalidParam('column');
        }

        $source = $app->managers->sources->find($id);

        $info = $app->managers->sources->getImport($source)->getFileColumnValuesInfo($column);

        if ($notLessThan = $app->request->get('not_less_than', false)) {
            $info = array_filter($info, function ($item) use ($notLessThan) {
                return $item['total'] >= $notLessThan;
            });
        }

//        if ($app->request->get('is_items', true)) {
        //@todo process is_items param...
//        }

        $app->response->setJSON(200, $info);
    }
}