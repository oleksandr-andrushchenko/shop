<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class ImportSourceSaveMainAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_SAVE_IMPORT_SOURCE_MAIN);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequestHttpException)->setInvalidParam('id');
        }

        $source = $app->managers->sources->find($id);

        $source->setName($app->request->get('name'))
            ->setFile($app->request->get('file'))
            ->setUri($app->request->get('uri'))
            ->setVendorId($app->request->get('vendor_id'))
            ->setClassName($app->request->get('class_name'))
            ->setDeliveryNotes($app->request->get('delivery_notes'))
            ->setSalesNotes($app->request->get('sales_notes'))
            ->setTechNotes($app->request->get('tech_notes'))
            ->setIsCron($app->request->get('is_cron'));

        $app->managers->sources->updateOne($source);

        $app->request->redirectBack();
    }
}