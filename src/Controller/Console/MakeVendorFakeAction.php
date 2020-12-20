<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use LogicException;
use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Entity\EntityException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

/**
 * @example php bin/console make-vendor-fake 6 1
 * Class MakeVendorFake
 * @package SNOWGIRL_SHOP\Controller\Console
 */
class MakeVendorFakeAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws BadRequestHttpException
     * @throws EntityException
     * @throws NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$vendorId = $app->request->get('param_1')) {
            throw (new BadRequestHttpException)->setInvalidParam('vendor_id');
        }

        if (!$vendor = $app->managers->vendors->find($vendorId)) {
            throw (new BadRequestHttpException)->setInvalidParam('vendor');
        }

        if ($vendor->isFake()) {
            throw new LogicException('vendor "' . $vendor->getName() . '" is already fake');
        }

        if (!$targetVendorId = $app->request->get('param_2')) {
            throw (new BadRequestHttpException)->setInvalidParam('target_vendor_id');
        }

        if (!$targetVendor = $app->managers->vendors->find($targetVendorId)) {
            throw (new BadRequestHttpException)->setInvalidParam('target_vendor');
        }

        if ($targetVendor->isFake()) {
            throw new LogicException('target vendor "' . $targetVendor->getName() . '" should not being fake');
        }

        $aff = $app->container->db->makeTransaction(function () use ($app, $vendor, $targetVendor) {
            $aff = $app->managers->items->updateMany(['is_in_stock' => 0], ['vendor_id' => $vendor->getId()]);
            $vendor->setTargetVendorId($targetVendor->getId());
            $app->managers->vendors->updateOne($vendor);
            return $aff;
        });

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            $aff ? "DONE: {$aff}" : 'FAILED',
        ]));
    }
}