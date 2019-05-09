<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 3/7/16
 * Time: 6:47 AM
 */

namespace SNOWGIRL_SHOP\Controller;

use SNOWGIRL_CORE\Response;
use SNOWGIRL_SHOP\App;
use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Manager\Item as ItemManager;
use SNOWGIRL_SHOP\Import;
use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Entity\Brand;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\Forbidden;
use SNOWGIRL_CORE\Exception\HTTP\MethodNotAllowed;
use SNOWGIRL_SHOP\Entity\Vendor;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_CORE\Entity\User;
use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalog;
use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalogManager;
use SNOWGIRL_SHOP\Entity\Page\Catalog\Custom as PageCatalogCustom;
use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttr;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;

use SNOWGIRL_SHOP\Manager\Category as CategoryManager;

ini_set('max_execution_time', 0);
ini_set('memory_limit', '4096M');

/**
 * Class Admin
 * @property App $app
 * @package SNOWGIRL_SHOP\Controller
 */
class Admin extends \SNOWGIRL_CORE\Controller\Admin
{
    protected function getDefaultAction()
    {
        if ($this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            $action = 'offers';
        } elseif ($this->app->request->getClient()->getUser()->isRole(User::ROLE_COPYWRITER)) {
            $action = 'catalog';
        } else {
            $action = 'logout';
        }

        return $action;
    }

    protected function getControlButtons()
    {
        return [
            [
                'text' => 'Страницы + Sitemap',
                'icon' => 'refresh',
                'class' => 'success',
                'action' => 'generate-pages-and-sitemap'
            ],
            [
                'text' => 'Sitemap',
                'icon' => 'refresh',
                'class' => 'info',
                'action' => 'generate-sitemap'
            ],
            [
                'text' => 'Rotate Cache',
                'icon' => 'refresh',
                'class' => 'warning',
                'action' => 'rotate-cache'
            ],
            [
                'text' => 'Rotate Sphinx',
                'icon' => 'refresh',
                'class' => 'default',
                'action' => 'rotate-sphinx'
            ],
        ];
    }

    /**
     * @throws Exception
     * @throws Forbidden
     */
    public function actionCatalog()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_COPYWRITER)) {
            throw new Forbidden;
        }

        $view = $this->app->views->getLayout(true);

        $content = $view->setContentByTemplate('@snowgirl-shop/admin/catalog.phtml', [
            'search' => $this->app->request->get('search'),
            'maxArticleLength' => $this->app->config->blog->article_length(2500),
            'client' => $this->app->request->getClient()->getUser(),
        ]);

        $page = (int)$this->app->request->get('page', 1);
        $size = (int)$this->app->request->get('size', 10);

        $manager = $this->app->managers->catalog->clear()
            ->setOffset(($page - 1) * $size)
            ->setLimit($size)
            ->calcTotal(true);

        if ($content->search) {
            $objects = $manager->getObjectsByQuery($content->search);
        } else {
            $objects = $manager->getObjects();
        }

        $total = $manager->getTotal();
        $manager->addLinkedObjects($objects, ['params_hash' => PageCatalogCustom::class]);

        $content->addParams([
            'manager' => $manager,
            'managerCustom' => $this->app->managers->catalogCustom,
            'pages' => $objects,
            'pager' => $this->app->views->pager([
                'link' => $this->app->router->makeLink('admin', [
                    'action' => 'catalog',
                    'priority' => isset($priorities) ? $priorities : null,
                    'search' => $content->search,
                    'page' => '{page}'
                ]),
                'total' => $total,
                'size' => $size,
                'page' => $page,
                'per_set' => 5,
                'param' => 'page'
            ], $view)
        ]);

        $this->app->response->setHTML(200, $view);
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     */
    public function actionPageCatalogCustomSeoText()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_COPYWRITER)) {
            throw new Forbidden;
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

//        D($this->app->request->getParams());

        $manager = $this->app->managers->catalog;

        /** @var PageCatalog $pageCatalog */

        if (!$pageCatalog = $manager->find($id)) {
            throw new NotFound;
        }

        $pageCatalogCustom = $manager->getPageCatalogCustom($pageCatalog);

        $clientId = $this->app->request->getClient()->getUser()->getId();

        if ($this->app->request->has('num')) {
            if (!$pageCatalogCustom) {
                throw new NotFound;
            }

            $texts = $pageCatalogCustom->getSeoTexts(true);
            $num = $this->app->request->get('num');

            if (!isset($texts[$num])) {
                throw new NotFound;
            }

            if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN) && $texts[$num]['user'] != $clientId) {
                throw new Forbidden;
            }
        } else {
            if ($this->app->request->isDelete()) {
                throw (new BadRequest)->setInvalidParam('num');
            }

            if ($pageCatalogCustom) {
                $texts = $pageCatalogCustom->getSeoTexts(true);
                $num = count($texts);
            } else {
                $pageCatalogCustom = $manager->makeCustom($pageCatalog);
                $texts = [];
                $num = 0;
            }

            $texts[$num] = [
                'h1' => '',
                'body' => '',
                'user' => $clientId,
                'active' => 0
            ];
        }

        return self::_exec(null, function () use ($manager, $pageCatalog, $pageCatalogCustom, $texts, $num, $clientId) {
            if ($this->app->request->isDelete()) {
                unset($texts[$num]);
                $texts = array_values($texts);
            } else {
                if ($isNew = (null === $num)) {
                    $num = 0;
                }

                $texts[$num]['h1'] = $this->app->request->get('h1');
                $texts[$num]['body'] = $this->app->request->get('body');

                if ($this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN)) {
                    $texts[$num]['active'] = $this->app->request->get('active');
                }
            }

            $pageCatalogCustom->setSeoTexts($texts);

            $this->app->managers->catalogCustom->save($pageCatalogCustom);

            $this->app->request->redirect($this->app->request->getReferer());
        });
    }

    /**
     * @return Response
     * @throws Forbidden
     * @throws NotFound
     */
    public function actionTogglePageCatalogCustomSeoTextActive()
    {
        if (!$this->app->managers->catalogCustom->isCanActiveSeoTexts($this->app->request->getClient()->getUser())) {
            throw new Forbidden;
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        /** @var PageCatalog $pageCatalog */

        if (!$pageCatalog = $this->app->managers->catalog->find($id)) {
            throw new NotFound;
        }

        if (!$pageCatalogCustom = $this->app->managers->catalog->getPageCatalogCustom($pageCatalog)) {
            throw new NotFound;
        }

        if (!$num = $this->app->request->has('num')) {
            throw (new BadRequest)->setInvalidParam('num');
        }

        $num = $this->app->request->get('num');
        $texts = $pageCatalogCustom->getSeoTexts(true);

        if (!isset($texts[$num])) {
            throw new NotFound;
        }

        $texts[$num]['active'] = $v = $texts[$num]['active'] ? false : true;
        $pageCatalogCustom->setSeoTexts($texts);

        $this->app->managers->catalogCustom->updateOne($pageCatalogCustom);

        return $this->app->response->setJSON(200, [
            'active' => $v
        ]);
    }

    public function actionBrands()
    {
        $this->app->request->redirectToRoute('admin', [
            'action' => 'database',
            'table' => Brand::getTable()
        ]);
    }

    /**
     * @return Response
     * @throws Exception
     * @throws Forbidden
     */
    public function actionOffers()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        $view = $this->app->views->getLayout(true);

        $content = $view->setContentByTemplate('@snowgirl-shop/admin/offers.phtml', [
            'vendors' => $this->app->managers->vendors->clear()->getObjects(),
            'vendorClasses' => $this->app->managers->vendors->getAdapterClasses(true),
            'importClasses' => $this->app->managers->sources->getImportClasses(true),
            'sourceTypes' => ImportSource::getColumns()['type']['range'],
            'importSources' => $this->app->managers->sources->clear()->getObjects()
        ]);

        $content->addParams($this->app->request->getParams());

        return $this->app->response->setHTML(200, $view);
    }

    /**
     * @throws Exception
     * @throws Forbidden
     */
    public function actionImportSource()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        if (!$source = $this->app->managers->sources->find($id)) {
            throw (new NotFound)->setNonExisting('source');
        }

        $view = $this->app->views->getLayout(true);
        $content = $view->setContentByTemplate('@snowgirl-shop/admin/import-source.phtml');

        $import = $this->app->managers->sources->getImport($source);

        $content->addParams([
            'source' => $source,
            'isOkLastImport' => $this->app->managers->importHistory->isOkLastImport($source),
            'name' => $this->app->request->get('name', $source->getName()),
            'file' => $this->app->request->get('file', $source->getFile()),
            'uri' => $this->app->request->get('uri', $source->getUri()),
            'vendors' => $this->app->managers->vendors->clear()->getObjects(),
            'vendorId' => $this->app->request->get(Vendor::getPk(), $source->getVendorId()),
            'importClass' => $this->app->request->get('class_name', $source->getClassName()),
            'cronImport' => $this->app->request->get('is_cron', $source->getIsCron()),
            'dbColumns' => $import->getItemColumnsToImport(),
            'dbRequiredColumns' => $import->getRequiredItemColumnsToImport(),
            'svaValues' => $import->getSvaValues($this->app),
            'importClasses' => $this->app->managers->sources->getImportClasses(true),
            'sourceTypes' => ImportSource::getColumns()['type']['range'],
            'deliveryNotes' => $this->app->request->get('delivery_notes', $source->getDeliveryNotes()),
            'salesNotes' => $this->app->request->get('sales_notes', $source->getSalesNotes()),
            'techNotes' => $this->app->request->get('tech_notes', $source->getTechNotes()),
            'mappingModifyTags' => $this->app->managers->sources->getFileMappingModifyTags($source),
            'mappingFileColumnsValuesInfo' => $import->getMappingFileColumnsValuesInfo($counts),
            'isShowModifiersItems' => $counts <= 100,
            'modifyNotLessThan' => 10,
            'mappingAutoFuncFor' => [Category::getPk()],
            'fileName' => $source->getFile(),
            'sva' => array_map(function ($component) {
                /** @var ItemAttr $component */
                return $component::getPk();
            }, PageCatalogManager::getSvaComponents())
        ]);

        if ($this->app->request->get('data', false)) {
            $page = $this->app->request->get('page', 1);
            $size = $this->app->request->get('size', 20);
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
            'importLimit' => $this->app->request->get('import-length', $total)
        ]);

        $this->app->response->setHTML(200, $view);
    }

    /**
     * @return Response
     * @throws Forbidden
     * @throws NotFound
     */
    public function actionImportSourceToggleCron()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        if (!$source = $this->app->managers->sources->find($id)) {
            throw new NotFound;
        }

        $source->setIsCron($source->isCron() ? 0 : 1);
        $this->app->managers->sources->updateOne($source);

        return $this->app->response->setJSON(200, [
            'is_cron' => $source->getIsCron()
        ]);
    }

    /**
     * @throws Forbidden
     */
    public function actionImportSourceRefresh()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        $source = $this->app->managers->sources->find($id);

        self::_exec('Свежая версия!', function () use ($source) {
            $this->app->managers->sources->getImport($source)->dropCache();
        });

        $this->app->request->redirect($this->app->request->getReferer());
    }

    /**
     * @return Response
     * @throws Exception\EntityAttr\Required
     * @throws Forbidden
     */
    public function actionImportSourceCopy()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        $source = $this->app->managers->sources->find($id);

        $new = new ImportSource;

        foreach ($source->getAttrs() as $k => $v) {
            if (ImportSource::getPk() != $k) {
                $new->set($k, $v);
            }
        }

        $new->setName($new->getName() . ' - copy');

        $this->app->managers->sources->insertOne($new);

        return $this->app->response->setJSON(200, [
            'id' => $new->getId()
        ]);
    }

    /**
     * @return Response
     * @throws Forbidden
     */
    public function actionImportSourceDelete()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN)) {
            throw new Forbidden;
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        $source = $this->app->managers->sources->find($id);

        $count = $this->app->managers->items->clear()
            ->setWhere([ImportSource::getPk() => $source->getId()])
            ->getCount();

        if ($count > 0) {
            return $this->app->response->setJSON(200, [
                'count' => $count
            ]);
        }

        $this->app->managers->sources->deleteOne($source);

        $this->app->views->getLayout(true)->addMessage('Поставщик <b>' . $source->getName() . '</b> удален!', Layout::MESSAGE_SUCCESS);

        return $this->app->response->setJSON(200);
    }

    /**
     * @return bool|Response
     * @throws Forbidden
     */
    public function actionImportSourceDeleteItems()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN)) {
            throw new Forbidden;
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        $source = $this->app->managers->sources->find($id);

        $this->app->response->setJSON(200);

        $view = $this->app->views->getLayout(true);

        if ($this->app->request->get('confirmed')) {
            if ($this->app->managers->sources->deleteItems($source)) {
                $view->addMessage('Предложения поставщика <b>' . $source->getName() . '</b> удалены!', Layout::MESSAGE_SUCCESS);
                $this->app->response->setJSON(200);
            }

            return true;
        }

        $count = $this->app->managers->items->clear()
            ->setWhere(['vendor_id' => $source->getVendorId()])
            ->getCount();

        if ($count > 0) {
            return $this->app->response->setJSON(200, [
                'count' => $count
            ]);
        }

        $view->addMessage('Не найдено предложений для <b>' . $source->getName() . '</b>', Layout::MESSAGE_INFO);

        return true;
    }

    /**
     * @throws Forbidden
     */
    public function actionImportSourceSaveMain()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        $source = $this->app->managers->sources->find($id);

        self::_exec('Настройки поставщика успешно обновлены', function () use ($source) {
            $source->setName($this->app->request->get('name'))
                ->setFile($this->app->request->get('file'))
                ->setUri($this->app->request->get('uri'))
                ->setVendorId($this->app->request->get('vendor_id'))
                ->setClassName($this->app->request->get('class_name'))
                ->setDeliveryNotes($this->app->request->get('delivery_notes'))
                ->setSalesNotes($this->app->request->get('sales_notes'))
                ->setTechNotes($this->app->request->get('tech_notes'))
                ->setIsCron($this->app->request->get('is_cron'));

            $this->app->managers->sources->updateOne($source);
        });

        $this->app->request->redirect($this->app->request->getReferer());
    }

    /**
     * @throws Forbidden
     */
    public function actionImportSourceSaveFilter()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        $source = $this->app->managers->sources->find($id);

        self::_exec('Фильтры файла поставщика успешно обновлены', function () use ($source) {
            $this->app->managers->sources->updateFileFilter($source, $this->app->request->get('filter', []));
        });

        $this->app->request->redirect($this->app->request->getReferer());
    }

    /**
     * @throws Forbidden
     */
    public function actionImportSourceSaveMapping()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        $source = $this->app->managers->sources->find($id);

        self::_exec('Маппинг данных файла в данные системы успешно обновлены', function () use ($source) {
            $this->app->managers->sources->updateFileMapping($source, $this->app->request->get('map', []));
        });

        $this->app->request->redirect($this->app->request->getReferer());
    }

    /**
     * @throws Forbidden
     */
    public function actionImportSourceImport()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        $source = $this->app->managers->sources->find($id);

        self::_exec('Импорт успешно завершен', function () use ($source) {
            $this->app->managers->sources->getImport($source)->run(
                $this->app->request->get('import-offset', 0),
                $this->app->request->get('import-length', 999999)
            );
        });

        $this->app->request->redirect($this->app->request->getReferer());
    }

    /**
     * @return Response
     */
    public function actionImportSourceGetMapFromPossibleValues()
    {
        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        if (!$column = $this->app->request->get('column')) {
            throw (new BadRequest)->setInvalidParam('column');
        }

        $source = $this->app->managers->sources->find($id);

        $info = $this->app->managers->sources->getImport($source)->getFileColumnValuesInfo($column);

        if ($notLessThan = $this->app->request->get('not_less_than', false)) {
            $info = array_filter($info, function ($item) use ($notLessThan) {
                return $item['total'] >= $notLessThan;
            });
        }

//        if ($this->app->request->get('is_items', true)) {
        //@todo process is_items param...
//        }

        return $this->app->response->setJSON(200, $info);
    }

    public function actionImportSourceGetMapToPossibleValues()
    {
        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        if (!$column = $this->app->request->get('column')) {
            throw (new BadRequest)->setInvalidParam('column');
        }

        $manager = $this->app->managers->getByEntityPk($column);
        $entity = $manager->getEntity()->getClass();

        return $this->app->response->setJSON(200, $this->app->utils->attrs->getIdToName($entity));
    }

    /**
     * @throws Forbidden
     */
    public function actionGeneratePagesAndSitemap()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        self::_exec('Страницы и sitemap успешно обновлены', function () {
//            App::increaseMemoryLimit();
            $this->app->seo->getPages()->update();
            $this->app->seo->getSitemap()->update();
        });

        $this->app->request->redirectToRoute('admin', 'generate-sitemap');
    }

    /**
     * @throws Exception
     * @throws Forbidden
     */
    public function actionCategories()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        $view = $this->app->views->getLayout(true);
        $view->setContentByTemplate('@snowgirl-shop/admin/categories.phtml', [
            'tree' => $this->app->managers->categories->makeTreeHtml()
        ]);

        $this->app->response->setHTML(200, $view);
    }

    /**
     * @throws Forbidden
     */
    public function actionCategoriesBuildTree()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        try {
            if ($this->app->utils->categories->doBuildTreeByNames($this->app->request->get('delimiter', '/'), $error)) {
                $this->app->views->getLayout(true)->addMessage('DONE', Layout::MESSAGE_SUCCESS);
            } else {
                $this->app->views->getLayout(true)->addMessage('FAILED: ' . $error, Layout::MESSAGE_ERROR);
            }
        } catch (\Exception $ex) {
            $this->app->views->getLayout(true)->addMessage('FAILED: ' . $ex->getMessage(), Layout::MESSAGE_ERROR);
        }

        $this->app->request->redirect($this->app->request->getReferer());
    }

    /**
     * @throws Exception\EntityAttr\Required
     * @throws Forbidden
     */
    public function actionFixBrandsUpperCase()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN)) {
            throw new Forbidden;
        }

        /** @var Brand $item */
        foreach ($this->app->managers->brands->clear()->getObjects() as $item) {
            $tmp = ucwords($item->getName());

            if ($tmp != $item->getName()) {
                $item->setName($tmp);
                $this->app->managers->brands->updateOne($item);
            }
        }

        $this->app->request->redirectToRoute('admin');
    }

    /**
     * @throws Forbidden
     */
    public function actionDeleteItemDuplicates()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN)) {
            throw new Forbidden;
        }

        return self::_exec('Дубляжи предложений удалены!', function () {
            /** @var ImportSource[] $sources */
            $sources = $this->app->managers->sources->clear()
                ->setColumns(ImportSource::getPk())
                ->getObjects();

            $aff = 0;

            foreach ($sources as $source) {
                $aff += $this->app->utils->import->doDeleteImportSourceItemsDuplicates($source);
            }

            return 'Удалено: ' . $aff;
        });
    }

    /**
     * @throws Forbidden
     */
    public function actionImportSourceDeleteDuplicateItems()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        return self::_exec('Дубликаты предложений успешно удалены', function () use ($id) {
            $source = $this->app->managers->sources->find($id);
            $aff = $this->app->utils->import->doDeleteImportSourceItemsDuplicates($source);
            return 'Удалено: ' . $aff;
        }, true);
    }

    /**
     * @throws Exception
     * @throws Forbidden
     */
    public function actionItemFixes()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        $view = $this->app->views->getLayout(true);

        $content = $view->setContentByTemplate('@snowgirl-shop/admin/item-fixes.phtml', [
            'columns' => Arrays::removeKeys(Item::getColumns(), ['upc', 'price', 'old_price', 'rating', 'uri']),
            'editableColumns' => Import::getPostImportEditableColumns(),
            'categories' => $this->app->managers->categories->clear()->setOrders(['name' => SORT_ASC])->getObjects(true),
            'countries' => $this->app->managers->countries->clear()->setOrders(['name' => SORT_ASC])->getObjects(true),
            'searchBy' => $searchBy = $this->app->request->get('search_by', false),
            'searchValue' => $searchValue = $this->app->request->get('search_value', false),
            'searchUseFulltext' => $searchUseFulltext = $this->app->request->get('search_use_fulltext', false),
            'orderBy' => $orderBy = $this->app->request->get('order_by', false),
            'orderValue' => $orderValue = $this->app->request->get('order_value', 'asc')
        ]);

        $pageNum = (int)$this->app->request->get('page', 1);
        $pageSize = (int)$this->app->request->get('size', 20);

        /** @var ItemManager $src */
        $src = $this->app->managers->items->clear();

        $db = $this->app->services->rdbms;

        $srcWhat = ['*'];
        $srcWhere = [];
        $srcOrder = [];
        $srcOffset = ($pageNum - 1) * $pageSize;
        $srcLimit = $pageSize;

        if (mb_strlen($searchBy) && mb_strlen($searchValue)) {
            if ($searchUseFulltext) {
                $query = $db->makeQuery($searchValue);
                $tmp = 'MATCH(' . $db->quote($searchBy) . ') AGAINST (? IN BOOLEAN MODE)';

                $srcWhat[] = new Expr($tmp . ' AS ' . $db->quote('relevance'), $query);
                $srcWhat[] = new Expr('CHAR_LENGTH(' . $db->quote($searchBy) . ') AS ' . $db->quote('length'));
                $srcWhere[] = new Expr($tmp, $query);
                $srcOrder['length'] = SORT_ASC;
                $srcOrder['relevance'] = SORT_DESC;
            } else {
                $srcWhere[$searchBy] = $searchValue;
            }
        }

        if ($orderBy && $orderValue) {
            $srcOrder[$orderBy] = [
                'asc' => SORT_ASC,
                'desc' => SORT_DESC
            ][$orderValue];
        }

        $src->setColumns($srcWhat)
            ->setWhere($srcWhere)
            ->setOrders($srcOrder)
            ->setOffset($srcOffset)
            ->setLimit($srcLimit)
            ->calcTotal(true);

        /** @var Item[] $items */
        $items = $src->getObjects(true);

        $content->items = $items;

        $tmp = [
            'brands' => [],
            'vendors' => []
        ];

        foreach ($items as $item) {
            $tmp['brands'][] = $item->getBrandId();
            $tmp['vendors'][] = $item->getVendorId();
        }

        $content->addParams([
            'brands' => $this->app->managers->brands->findMany(array_unique($tmp['brands'])),
            'vendors' => $this->app->managers->vendors->findMany(array_unique($tmp['vendors'])),
            'mvaEntities' => PageCatalogManager::getMvaComponents()
        ]);

        $tmp = [];
        $tmp2 = [];

        foreach ($content->mvaEntities as $attrEntityClass) {
            /** @var ItemAttr $attrEntityClass */
            $attrEntityClass = new $attrEntityClass;
            /** @var ItemAttrManager $attrManagerClass */
            $attrManagerClass = $this->app->managers->getByEntityClass($attrEntityClass);

            $table = $attrEntityClass::getTable();
            $tmp[$table] = $attrManagerClass->getMva(array_keys($items), $attrValuesNames);
            $tmp2[$table] = $attrValuesNames;
        }

        $content->addParams([
            'mvaValues' => $tmp,
            'mvaValuesNames' => $tmp2,
            'pager' => $this->app->views->pager([
                'link' => $this->app->router->makeLink('admin', array_merge($this->app->request->getGetParams(), [
                    'action' => 'item-fixes',
                    'page' => '{page}'
                ])),
                'total' => $src->getTotal(),
                'size' => $pageSize,
                'page' => $pageNum,
                'per_set' => 5,
                'param' => 'page'
            ], $view)
        ]);

        $this->app->response->setHTML(200, $view);
    }

    /**
     * @todo add custom entity add functionality... (что-бы, например, к "Сумки" можно было присобачить "сумочка", а к "Часы" - "Наручные часы")
     * @throws Exception
     * @throws Forbidden
     */
    public function actionCategoryFixes()
    {
        if (!$this->app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        $view = $this->app->views->getLayout(true);

        $content = $view->setContentByTemplate('@snowgirl-shop/admin/category-fixes.phtml', [
            'columns' => array_diff(array_keys(Category::getColumns()), []),
            'editableColumns' => array_diff(array_keys(Category::getColumns()), []),
            'categories' => $this->app->managers->categories->clear()->setOrders(['name' => SORT_ASC])->getObjects(true),
            'searchBy' => $searchBy = $this->app->request->get('search_by', false),
            'searchValue' => $searchValue = $this->app->request->get('search_value', false),
            'searchUseFulltext' => $searchUseFulltext = $this->app->request->get('search_use_fulltext', false),
            'searchWithEntities' => $this->app->request->get('search_entities', false),
            'searchWithNonActiveEntities' => $this->app->request->get('search_non_active_entities', false),
            'orderBy' => $orderBy = $this->app->request->get('order_by', false),
            'orderValue' => $orderValue = $this->app->request->get('order_value', 'asc')
        ]);

        $pageNum = (int)$this->app->request->get('page', 1);
        $pageSize = (int)$this->app->request->get('size', 20);

        /** @var CategoryManager $src */
        $src = $this->app->managers->categories->clear();

        $db = $this->app->services->rdbms;

        $srcWhat = ['*'];
        $srcWhere = [];
        $srcOrder = [];
        $srcOffset = ($pageNum - 1) * $pageSize;
        $srcLimit = $pageSize;

        if (mb_strlen($searchBy) && mb_strlen($searchValue)) {
            if ($searchUseFulltext) {
                $query = $db->makeQuery($searchValue);
                $tmp = 'MATCH(' . $db->quote($searchBy) . ') AGAINST (? IN BOOLEAN MODE)';

                $srcWhat[] = new Expr($tmp . ' AS ' . $db->quote('relevance'), $query);
                $srcWhat[] = new Expr('CHAR_LENGTH(' . $db->quote($searchBy) . ') AS ' . $db->quote('length'));
                $srcWhere[] = new Expr($tmp, $query);
                $srcOrder['length'] = SORT_ASC;
                $srcOrder['relevance'] = SORT_DESC;
            } else {
                $srcWhere[$searchBy] = $searchValue;
            }
        }

        $manager = $this->app->managers->categoriesToEntities->clear();

        if ($content->searchWithEntities) {
            $srcWhere['category_id'] = $manager->getCategoryList();
        }

        if ($content->searchWithNonActiveEntities) {
            $srcWhere['category_id'] = $manager->getCategoryListWithNonActiveItems();
        }

        if ($orderBy && $orderValue) {
            $srcOrder[$orderBy] = [
                'asc' => SORT_ASC,
                'desc' => SORT_DESC
            ][$orderValue];
        }

        $src->setColumns($srcWhat)
            ->setWhere($srcWhere)
            ->setOrders($srcOrder)
            ->setOffset($srcOffset)
            ->setLimit($srcLimit)
            ->calcTotal(true);

        $content->addParams([
            'items' => $items = $src->getObjects(true),
            'itemEntities' => $this->app->managers->categoriesToEntities->getItemsGroupByCategories(['category_id' => array_keys($items)]),
            'itemItems' => $this->app->managers->items->getFirstItemsFromEachCategory(['category_id' => array_keys($items)], 5),
            'categoryPicker' => $this->app->managers->categories->makeTagPicker(null, false, [], $view),
            'tagsPicker' => $this->app->managers->tags->makeTagPicker(null, true, [], $view),
            'pager' => $this->app->views->pager([
                'link' => $this->app->router->makeLink('admin', array_merge($this->app->request->getGetParams(), [
                    'action' => 'category-fixes',
                    'page' => '{page}'
                ])),
                'total' => $src->getTotal(),
                'size' => $pageSize,
                'page' => $pageNum,
                'per_set' => 5,
                'param' => 'page'
            ], $view)
        ]);

        $this->app->response->setHTML(200, $view);
    }

    public function actionTransferItemsByAttrs()
    {
        if (!$this->app->request->isPost()) {
            throw (new MethodNotAllowed)->setValidMethod('post');
        }

        if (!$source = $this->app->request->get('source')) {
            throw (new BadRequest)->setInvalidParam('source');
        }

        if (!$target = $this->app->request->get('target')) {
            throw (new BadRequest)->setInvalidParam('target');
        }

        $aff = $this->app->utils->items->doTransferByAttrs($source, $target);
        $view = 'affected: ' . $aff;

        return $this->app->response->setJSON(200, $view);
    }
}
