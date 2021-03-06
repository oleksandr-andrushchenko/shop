<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_SHOP\RBAC;

class CopyImportSourceAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_COPY_IMPORT_SOURCE);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequestHttpException)->setInvalidParam('id');
        }

        $source = $app->managers->sources->find($id);

        $new = new ImportSource;

        foreach ($source->getAttrs() as $k => $v) {
            if (ImportSource::getPk() != $k) {
                $new->set($k, $v);
            }
        }

        $new->setName($new->getName() . ' - copy');

        $app->managers->sources->insertOne($new);

        $app->response->setJSON(200, [
            'id' => $new->getId()
        ]);
    }
}