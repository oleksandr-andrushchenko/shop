<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttr;
use SNOWGIRL_SHOP\RBAC;

class ImportSourceAction
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

        $view = $app->views->getLayout(true);
        $content = $view->setContentByTemplate('@shop/admin/import-source.phtml');

        $import = $app->managers->sources->getImport($source);

        $content->addParams([
            'source' => $source,
            'isOkLastImport' => $app->managers->importHistory->isOkLastImport($source),
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
            'sourceTypes' => $app->managers->sources->getEntity()->getColumns()['type']['range'],
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
            'importLimit' => $app->request->get('import-length', $total)
        ]);

        $app->response->setHTML(200, $view);
    }
}