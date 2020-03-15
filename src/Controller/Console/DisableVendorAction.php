<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class DisableVendorAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$vendorId = $app->request->get('param_1')) {
            throw (new BadRequestHttpException)->setInvalidParam('vendor_id');
        }

        if (!$vendor = $app->managers->vendors->find($vendorId)) {
            throw (new NotFoundHttpException)->setNonExisting('vendor_id');
        }

        $vendor->setIsActive(false);

        $app->managers->vendors->updateOne($vendor);

        $app->response->setBody(implode("\r\n", [
            __CLASS__,
            'DONE'
        ]));
    }
}