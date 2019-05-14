<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 9:52 PM
 */

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Entity\User;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\Forbidden;
use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;

class ImportSourceDeleteItemsAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     *
     * @return bool|\SNOWGIRL_CORE\Response
     * @throws Forbidden
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN)) {
            throw new Forbidden;
        }

        if (!$id = $app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
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