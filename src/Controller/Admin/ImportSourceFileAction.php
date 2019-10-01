<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttr;
use SNOWGIRL_SHOP\RBAC;

class ImportSourceFileAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_IMPORT_SOURCE_PAGE);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        if (!$source = $app->managers->sources->find($id)) {
            throw (new NotFound)->setNonExisting('source');
        }

        $import = $app->managers->sources->getImport($source);

        $view = $app->views->getLayout(true);

        $content = $view->setContentByTemplate('@shop/admin/import-source-file.phtml');

        $content->addParams([
            'source' => $source,
            'remote' => $import->getFilename(),
            'local' => $import->getDownloadedCsvFileName(),
            'content' => file_get_contents($import->getDownloadedCsvFileName())
        ]);

        $app->response->setHTML(200, $view);
    }
}