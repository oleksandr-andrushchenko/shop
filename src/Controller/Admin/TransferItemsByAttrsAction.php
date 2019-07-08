<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\MethodNotAllowed;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class TransferItemsByAttrsAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_TRANSFER_ITEMS_BY_ATTRS);

        if (!$app->request->isPost()) {
            throw (new MethodNotAllowed)->setValidMethod('post');
        }

        if (!$source = $app->request->get('source')) {
            throw (new BadRequest)->setInvalidParam('source');
        }

        if (!$target = $app->request->get('target')) {
            throw (new BadRequest)->setInvalidParam('target');
        }

        $aff = $app->utils->items->doTransferByAttrs($source, $target);
        $view = 'affected: ' . $aff;

        $app->response->setJSON(200, $view);
    }
}