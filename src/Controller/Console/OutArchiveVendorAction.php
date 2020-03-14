<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Console as App;

class OutArchiveVendorAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$vendorId = $app->request->get('param_1')) {
            throw (new BadRequestHttpException)->setInvalidParam('vendor_id');
        }

        if (!$vendor = $app->managers->vendors->find($vendorId)) {
            throw (new NotFoundHttpException)->setNonExisting('vendor');
        }

//        $aff1 = $app->managers->vendors->updateOne($vendor->setIsActive(true));
        $aff2 = $app->managers->sources->updateMany(['is_cron' => 1], ['vendor_id' => $vendor->getId()]);
        $aff3 = $app->utils->items->doOutArchiveTransfer(['vendor_id' => $vendor->getId()]);

        $app->response->setBody(implode("\r\n", [
            __CLASS__,
//            'Vendor activate on: ' . var_export($aff1, true),
            'Sources cron on: ' . var_export($aff2, true),
            'Affected: ' . var_export($aff3, true)
        ]));
    }
}