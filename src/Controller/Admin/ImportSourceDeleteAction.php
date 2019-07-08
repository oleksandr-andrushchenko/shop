<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_SHOP\RBAC;

class ImportSourceDeleteAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_DELETE_IMPORT_SOURCE);

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