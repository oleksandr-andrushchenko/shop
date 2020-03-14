<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Import;
use SNOWGIRL_SHOP\Manager\Item as ItemManager;
use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalogManager;
use SNOWGIRL_SHOP\Entity\Item\Attr as ItemAttr;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;
use SNOWGIRL_SHOP\RBAC;

class ItemFixesAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_ITEM_FIXES_PAGE);

        $view = $app->views->getLayout(true);

        $content = $view->setContentByTemplate('@shop/admin/item-fixes.phtml', [
            'columns' => Arrays::removeKeys($app->managers->items->getEntity()->getColumns(), ['partner_item_id', 'price', 'old_price', 'rating', 'uri']),
            'editableColumns' => Import::getPostEditableColumns(),
            'categories' => $app->managers->categories->clear()->setOrders(['name' => SORT_ASC])->getObjects(true),
            'countries' => $app->managers->countries->clear()->setOrders(['name' => SORT_ASC])->getObjects(true),
            'searchBy' => $searchBy = $app->request->get('search_by', false),
            'searchValue' => $searchValue = $app->request->get('search_value', false),
            'searchUseFulltext' => $searchUseFulltext = $app->request->get('search_use_fulltext', false),
            'orderBy' => $orderBy = $app->request->get('order_by', false),
            'orderValue' => $orderValue = $app->request->get('order_value', 'asc')
        ]);

        $pageNum = (int)$app->request->get('page', 1);
        $pageSize = (int)$app->request->get('size', 20);

        $src = $app->managers->items->clear();

        $db = $app->container->db;

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
            'brands' => $app->managers->brands->findMany(array_unique($tmp['brands'])),
            'vendors' => $app->managers->vendors->findMany(array_unique($tmp['vendors'])),
            'mvaEntities' => $app->managers->catalog->getMvaComponents()
        ]);

        $tmp = [];
        $tmp2 = [];

        foreach ($content->mvaEntities as $attrEntityClass) {
            /** @var ItemAttr $attrEntityClass */
            $attrEntityClass = new $attrEntityClass;
            /** @var ItemAttrManager $attrManagerClass */
            $attrManagerClass = $app->managers->getByEntityClass($attrEntityClass);

            $table = $attrEntityClass::getTable();
            $tmp[$table] = $attrManagerClass->getMva(array_keys($items), $attrValuesNames);
            $tmp2[$table] = $attrValuesNames;
        }

        $content->addParams([
            'mvaValues' => $tmp,
            'mvaValuesNames' => $tmp2,
            'pager' => $app->views->pager([
                'link' => $app->router->makeLink('admin', array_merge($app->request->getGetParams(), [
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

        $app->response->setHTML(200, $view);
    }
}