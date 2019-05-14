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
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;

class ImportSourceDeleteAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     *
     * @return \SNOWGIRL_CORE\Response
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

        $count = $app->managers->items->clear()
            ->setWhere([ImportSource::getPk() => $source->getId()])
            ->getCount();

        if ($count > 0) {
            return $app->response->setJSON(200, [
                'count' => $count
            ]);
        }

        $app->managers->sources->deleteOne($source);

        $app->views->getLayout(true)->addMessage('Поставщик <b>' . $source->getName() . '</b> удален!', Layout::MESSAGE_SUCCESS);

        $app->response->setJSON(200);
    }
}