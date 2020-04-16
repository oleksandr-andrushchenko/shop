<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class InArchiveVendorAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$vendorId = $app->request->get('param_1')) {
            throw (new BadRequestHttpException)->setInvalidParam('vendor_id');
        }

        if (!$vendor = $app->managers->vendors->find($vendorId)) {
            throw (new NotFoundHttpException)->setNonExisting('vendor');
        }

//        $aff1 = $app->managers->vendors->updateOne($vendor->setIsActive(false));
        $aff2 = $app->managers->sources->updateMany(['is_cron' => 0], ['vendor_id' => $vendor->getId()]);
        $aff3 = $app->utils->items->doInArchiveTransfer(['vendor_id' => $vendor->getId()]);

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
//            'Vendor activate off: ' . var_export($aff1, true),
            'Sources cron off: ' . var_export($aff2, true),
            'Affected: ' . var_export($aff3, true),
        ]));
    }
}