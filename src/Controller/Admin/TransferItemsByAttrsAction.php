<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 9:55 PM
 */

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\MethodNotAllowed;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;

class TransferItemsByAttrsAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     *
     * @throws \SNOWGIRL_CORE\Exception\HTTP\Forbidden
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

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