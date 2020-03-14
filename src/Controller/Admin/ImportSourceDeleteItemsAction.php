<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class ImportSourceDeleteItemsAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_ALL);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequestHttpException)->setInvalidParam('id');
        }

        $source = $app->managers->sources->find($id);

        $app->response->setJSON(200);

        $view = $app->views->getLayout(true);

        if ($app->request->get('confirmed')) {
            if ($app->managers->sources->deleteItems($source)) {
                $view->addMessage('Предложения поставщика <b>' . $source->getName() . '</b> удалены!', Layout::MESSAGE_SUCCESS);
                $app->response->setJSON(200);
            }

            return true;
        }

        $count = $app->managers->items->clear()
            ->setWhere(['vendor_id' => $source->getVendorId()])
            ->getCount();

        if ($count > 0) {
            return $app->response->setJSON(200, [
                'count' => $count
            ]);
        }

        $view->addMessage('Не найдено предложений для <b>' . $source->getName() . '</b>', Layout::MESSAGE_INFO);
    }
}