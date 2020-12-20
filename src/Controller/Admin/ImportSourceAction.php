<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\RBAC;

class ImportSourceAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws \SNOWGIRL_CORE\Exception
     * @throws \SNOWGIRL_CORE\Http\Exception\ForbiddenHttpException
     */
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

        $content = $view->setContentByTemplate('@shop/admin/import-source.phtml');

        $content->addParams([
            'source' => $source,
            'lastImport' => $app->managers->importHistory->getLast($source),
            'name' => $app->request->get('name', $source->getName()),
            'file' => $app->request->get('file', $source->getFile()),
            'uri' => $app->request->get('uri', $source->getUri()),
            'vendors' => $app->managers->vendors->clear()->getObjects(),
            'vendorId' => $app->request->get($app->managers->vendors->getEntity()->getPk(), $source->getVendorId()),
            'importClass' => $app->request->get('class_name', $source->getClassName()),
            'cronImport' => $app->request->get('is_cron', $source->getIsCron()),
            'dbColumns' => $import->getItemColumns(),
            'dbRequiredColumns' => $import->getRequiredItemColumns(),
            'svaValues' => $import->getSvaValues($app),
            'importClasses' => $app->managers->sources->getImportClasses(true),
            'deliveryNotes' => $app->request->get('delivery_notes', $source->getDeliveryNotes()),
            'salesNotes' => $app->request->get('sales_notes', $source->getSalesNotes()),
            'techNotes' => $app->request->get('tech_notes', $source->getTechNotes()),
            'mappingModifyTags' => $app->managers->sources->getFileMappingModifyTags($source),
            'mappingFileColumnsValuesInfo' => $import->getMappingFileColumnsValuesInfo($counts),
            'isShowModifiersItems' => $counts <= 100,
            'modifyNotLessThan' => 10,
            'mappingAutoFuncFor' => [$app->managers->categories->getEntity()->getPk()],
            'fileName' => $source->getFile(),
            'sva' => array_keys($app->managers->catalog->getSvaPkToTable())
        ]);

        if ($app->request->get('data', false)) {
            $page = $app->request->get('page', 1);
            $size = $app->request->get('size', 20);
            $file = $import->getData($page, $size);

            $content->addParams([
                'data' => $file->data,
                'totalPages' => $file->totalPages,
                'page' => $page
            ]);

            $columns = $file->columns;
            $total = $file->totalItems;
        } else {
            $columns = $import->getMeta()['columns'];
            $total = 999999;
        }

        $content->addParams([
            'fileColumns' => $columns,
            'total' => $total,
        ]);

        $app->response->setHTML(200, $view);
    }
}