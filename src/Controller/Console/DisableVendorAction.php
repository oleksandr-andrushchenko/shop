<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_SHOP\App\Console as App;

class DisableVendorAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$vendorId = $app->request->get('param_1')) {
            throw (new BadRequest)->setInvalidParam('vendor_id');
        }

        if (!$vendor = $app->managers->vendors->find($vendorId)) {
            throw (new NotFound)->setNonExisting('vendor_id');
        }

        $vendor->setIsActive(false);

        $app->managers->vendors->updateOne($vendor);

        $app->response->setBody(implode("\r\n", [
            __CLASS__,
            'DONE'
        ]));
    }
}