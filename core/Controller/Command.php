<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 3/7/16
 * Time: 6:47 AM
 */

namespace SNOWGIRL_SHOP\Controller;

use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_CORE\Image;
use SNOWGIRL_SHOP\App;
use SNOWGIRL_SHOP\Import;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_SHOP\Item\FixWhere;

/**
 * Class Command
 *
 * @package SNOWGIRL_SHOP\Controller
 * @property App app
 */
class Command extends \SNOWGIRL_CORE\Controller\Command
{
    public function actionImportAll(ImportSource $importSource = null)
    {
//        $this->app->configMaster = null;

        $rotate = $this->app->request->get('param_1', true);

//        $this->app->services->mcms->disable();

        Import::factoryAndRun($this->app, $importSource);

        if ($rotate) {
            $this->output('::actionRotateFtdbms');
            //@todo...
//            $this->actionRotateFtdbms();
            $this->actionIndexElastic();
            $this->output('::actionRotateMcms');
            $this->actionRotateMcms();
        }
    }

    /**
     * @throws Exception
     */
    public function actionImport()
    {
//        $this->app->configMaster = null;

        if (!$importSourceId = $this->app->request->get('param_1')) {
            throw (new BadRequest)->setInvalidParam('import_source_id');
        }

        if (!$importSource = $this->app->managers->sources->find($importSourceId)) {
            throw (new NotFound)->setNonExisting('import_source_id');
        }

        if (!$importSource->isCron()) {
            throw new Exception('not in cron');
        }

        $this->app->request->set('param_1', $this->app->request->get('param_2', false));

        $this->actionImportAll($importSource);
    }

    public function actionSyncCategoriesTree()
    {
        $this->output('::actionSyncCategoriesTree');
        $this->output($this->app->managers->categories->syncTree() ? 'DONE' : 'FAILED');
    }

    public function actionUpdateCategoriesEntities()
    {
        $this->output('::actionUpdateCategoriesEntities');
        $this->output($this->app->managers->categoriesToEntities->createTableAndFill() ? 'DONE' : 'FAILED');
    }

    /**
     * #1 created_at_from_delta - int: created_at > time - delta
     * #2 created_at_to_delta - int: created_at < time - delta
     * #3 updated_at_from_delta - int: updated_at > time - delta
     * #4 updated_at_to_delta - int: updated_at < time - delta
     * #5 import_sources - int[]: comma separated ids
     * #6 or_between_created_and_updated - bool: "or" or "and" between created and updated clauses
     */
    public function actionDeleteItemsWithInvalidCategories()
    {
        $output = $this->app->utils->items->doDeleteItemsWithInvalidCategories(
            (new FixWhere($this->app))
                ->setCreatedAtFrom($this->app->request->get('param_1'))
                ->setCreatedAtTo($this->app->request->get('param_2'))
                ->setUpdatedAtFrom($this->app->request->get('param_3'))
                ->setUpdatedAtTo($this->app->request->get('param_4'))
                ->setSources(array_map('trim', explode(',', $this->app->request->get('param_5'))))
                ->setOrBetweenCreatedAndUpdated($this->app->request->get('param_6'))
        );

        $this->output($output ? 'DONE' : 'FAILED');
    }

    /**
     * #1 attr_tables - string[]: comma separated attributes table names
     * #1 created_at_from_delta - int: created_at > time - delta
     * #2 created_at_to_delta - int: created_at < time - delta
     * #3 updated_at_from_delta - int: updated_at > time - delta
     * #4 updated_at_to_delta - int: updated_at < time - delta
     * #5 import_sources - int[]: comma separated ids
     * #6 or_between_created_and_updated - bool: "or" or "and" between created and updated clauses
     */
    public function actionAddItemsMultiValueAttrs()
    {
        $output = $this->app->utils->attrs->doAddMvaByInclusions(
            (new FixWhere($this->app))
                ->setCreatedAtFrom($this->app->request->get('param_2'))
                ->setCreatedAtTo($this->app->request->get('param_3'))
                ->setUpdatedAtFrom($this->app->request->get('param_4'))
                ->setUpdatedAtTo($this->app->request->get('param_5'))
                ->setSources(array_map('trim', explode(',', $this->app->request->get('param_6'))))
                ->setOrBetweenCreatedAndUpdated($this->app->request->get('param_7')),
            array_map('trim', explode(',', $this->app->request->get('param_1', '')))
        );

        $this->output($output ? 'DONE' : 'FAILED');
    }

    /**
     * #1 created_at_from_delta - int: created_at > time - delta
     * #2 created_at_to_delta - int: created_at < time - delta
     * #3 updated_at_from_delta - int: updated_at > time - delta
     * #4 updated_at_to_delta - int: updated_at < time - delta
     * #5 import_sources - int[]: comma separated ids
     * #6 or_between_created_and_updated - bool: "or" or "and" between created and updated clauses
     */
    public function actionFixItemsCategories()
    {
        $output = $this->app->utils->items->doFixItemsCategories(
            (new FixWhere($this->app))
                ->setCreatedAtFrom($this->app->request->get('param_1'))
                ->setCreatedAtTo($this->app->request->get('param_2'))
                ->setUpdatedAtFrom($this->app->request->get('param_3'))
                ->setUpdatedAtTo($this->app->request->get('param_4'))
                ->setSources(array_map('trim', explode(',', $this->app->request->get('param_5'))))
                ->setOrBetweenCreatedAndUpdated($this->app->request->get('param_6'))

        );

        $this->output($output ? 'DONE' : 'FAILED');
    }

    public function actionRotateCategoriesTree()
    {
        $this->output('::actionSyncCategoriesTree');
        $this->actionSyncCategoriesTree();
    }

    public function _actionRotateMcms()
    {
        $this->output('::actionRotateCategoriesTree');
        $this->actionRotateCategoriesTree();
        parent::actionRotateMcms();
    }

    public function actionUpdateItemsOrders()
    {
        $this->output('::actionUpdateItemsOrders');
        return $this->output($this->app->utils->items->doUpdateItemsOrders() ? 'DONE' : 'FAILED');
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function actionDisableVendor()
    {
        $this->output('::actionDisableVendor');

        if (!$vendorId = $this->app->request->get('param_1')) {
            throw (new BadRequest)->setInvalidParam('vendor_id');
        }

        if (!$vendor = $this->app->managers->vendors->find($vendorId)) {
            throw (new NotFound)->setNonExisting('vendor_id');
        }

        $vendor->setIsActive(false);

        $this->app->managers->vendors->updateOne($vendor);

        return $this->output('DONE');
    }

    protected function getSimpleItemsWhere()
    {
        if (!$whereKey = $this->app->request->get('param_1')) {
            throw (new BadRequest)->setInvalidParam('where_key');
        }

        if (!$this->app->managers->items->getEntity()->hasAttr($whereKey)) {
            throw (new NotFound)->setNonExisting('where_key');
        }

        if (!$this->app->request->has('param_2')) {
            throw (new BadRequest)->setInvalidParam('where_value');
        }

        $whereValue = $this->app->request->get('param_2');

        return [$whereKey => $whereValue];
    }

    public function actionItemInArchiveTransfer()
    {
        $this->output('::actionItemInArchiveTransfer');
        $where = $this->getSimpleItemsWhere();

        return $this->output($this->app->utils->items->doInArchiveTransfer($where) ? 'DONE' : 'FAILED');
    }

    public function actionItemOutArchiveTransfer()
    {
        $this->output('::actionItemOutArchiveTransfer');
        $where = $this->getSimpleItemsWhere();

        return $this->output($this->app->utils->items->doOutArchiveTransfer($where) ? 'DONE' : 'FAILED');
    }

    public function actionFixItemArchiveMvaValues()
    {
        return $this->output(($aff = $this->app->utils->items->doFixArchiveMvaValues()) ? "DONE: {$aff}" : 'FAILED');
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function actionAddItemsImportSourceId()
    {
        $this->output('::actionAddItemsImportSourceId');
        return $this->output($this->app->utils->items->doAddImportSourceId() ? 'DONE' : 'FAILED');
    }

    public function actionFixItemsDuplicates()
    {
        $this->output('::actionFixItemsDuplicates');

        if (!$importSourceId = $this->app->request->get('param_1')) {
            throw (new BadRequest)->setInvalidParam('import_source_id');
        }

        return $this->output($this->app->utils->items->doFixDuplicates($importSourceId) ? 'DONE' : 'FAILED');
    }

    public function actionDeleteItemsNonExistingMva()
    {
        $this->output('::actionDeleteItemsNonExistingMva');
        return $this->output($this->app->utils->attrs->doDeleteNonExistingItemsMva() ? 'DONE' : 'FAILED');
    }

    public function actionMigrateCatalogCustomPages()
    {
        $this->output('::actionMigrateCatalogCustomPages');
        return $this->output($this->app->utils->catalog->doMigrateCatalogToCustom() ? 'DONE' : 'FAILED');
    }

    public function actionAddItemsArchiveImportSourceId()
    {
        $this->output('::actionAddItemsArchiveImportSourceId');
        return $this->output($this->app->utils->items->doAddArchiveImportSourceId() ? 'DONE' : 'FAILED');
    }

    public function actionTestAsosImage()
    {
        $first = 'http://images.asos-media.com/inv/media/6/5/1/2/9602156/blue/image1xxl.jpg';
        $error = null;

        if (!$image = Image::download($first, null, $error)) {
            return $this->output('FAILED: ' . $error);
        }

        return $this->output('DONE: ' . $image);
    }

    public function actionItemsInMongoTransfer()
    {
        $aff = $this->app->utils->items->doInMongoTransfer();
        return $this->output(is_int($aff) ? "DONE: {$aff}" : 'FAILED');
    }

    public function actionAttrsInMongoTransfer()
    {
        $attrs = ($tmp = $this->app->request->get('param_1')) ? explode(',', $tmp) : [];
        $aff = $this->app->utils->attrs->doInMongoTransfer($attrs);
        return $this->output(is_int($aff) ? "DONE: {$aff}" : 'FAILED');
    }

    public function actionDeleteItemsWithBadAttrs()
    {
        $aff = $this->app->utils->items->doDeleteWithNonExistingCategories();
        $this->output(is_int($aff) ? "DONE[non-existing-categories]={$aff}" : 'FAILED');

        $aff = $this->app->utils->items->doDeleteWithNonExistingBrands();
        $this->output(is_int($aff) ? "DONE[non-existing-brands]={$aff}" : 'FAILED');

        return true;
    }

    public function actionFixItemsWithNonExistingAttrs()
    {
        $aff = $this->app->utils->items->doFixWithNonExistingAttrs();
        return $this->output(is_int($aff) ? "DONE: {$aff}" : 'FAILED');
    }

    public function actionGenerateCatalogTexts()
    {
        $aff = $this->app->utils->catalog->doGenerateTexts();
        return $this->output(is_int($aff) ? "DONE: {$aff}" : 'FAILED');
    }

    public function actionIndexItemElastic()
    {
        return $this->output(($aff = $this->app->utils->items->doIndexElastic()) ? "DONE: {$aff}" : 'FAILED');
    }

    public function actionIndexCatalogElastic()
    {
        return $this->output(($aff = $this->app->utils->catalog->doIndexElastic()) ? "DONE: {$aff}" : 'FAILED');
    }

    public function actionIndexElastic()
    {
        $this->actionIndexItemElastic();
        $this->actionIndexCatalogElastic();

        return $this->output('DONE');
    }
}
