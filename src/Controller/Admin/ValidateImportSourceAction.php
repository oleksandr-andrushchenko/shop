<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttr;
use SNOWGIRL_SHOP\RBAC;

class ValidateImportSourceAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_IMPORT_SOURCE_PAGE);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequestHttpException)->setInvalidParam('id');
        }

        if (!$source = $app->managers->sources->find($id)) {
            throw (new NotFoundHttpException)->setNonExisting('source');
        }

        $import = $app->managers->sources->getImport($source);

        $view = $app->views->getLayout(true);

        $content = $view->setContentByTemplate('@shop/admin/validate-import-source.phtml');

        $content->addParams([
            'firstFilteredRow' => false,
            'firstPassedRow' => false,
            'firstPassedValues' => false
        ]);

        foreach ($import->getFilteredFile() as $row) {
            $content->firstFilteredRow = $row;
            break;
        }

        $import->walkImport(false, function ($row, $values) use (&$content) {
            $content->firstPassedRow = $row;
            $content->firstPassedValues = $values;
            return false;
        });

        $content->addParams([
            'source' => $source,
            'columns' => $columns = $import->getMeta()['columns'],
            'filterColumns' => $filterColumns = array_keys($source->getFileFilter(true)),
            'filterColumnsDiff' => array_diff($filterColumns, $columns),
            'mappingColumns' => $mappingColumns = array_map(function ($mapping) {
                return $mapping['column'];
            }, $source->getFileMapping(true)),
            'mappingColumnsDiff' => array_diff($mappingColumns, $columns)
        ]);

        $app->response->setHTML(200, $view);
    }
}