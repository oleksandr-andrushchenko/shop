<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Manager\Category as CategoryManager;
use SNOWGIRL_SHOP\RBAC;

class CategoryFixesAction
{
    use PrepareServicesTrait;

    /**
     * @todo add custom entity add functionality... (что-бы, например, к "Сумки" можно было присобачить "сумочка", а к
     *       "Часы" - "Наручные часы")
     *
     * @param App $app
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_CATEGORY_FIXES_PAGE);

        $view = $app->views->getLayout(true);

        $content = $view->setContentByTemplate('@shop/admin/category-fixes.phtml', [
            'columns' => array_diff(array_keys(Category::getColumns()), []),
            'editableColumns' => array_diff(array_keys(Category::getColumns()), []),
            'categories' => $app->managers->categories->clear()->setOrders(['name' => SORT_ASC])->getObjects(true),
            'searchBy' => $searchBy = $app->request->get('search_by', false),
            'searchValue' => $searchValue = $app->request->get('search_value', false),
            'searchUseFulltext' => $searchUseFulltext = $app->request->get('search_use_fulltext', false),
            'searchWithEntities' => $app->request->get('search_entities', false),
            'searchWithNonActiveEntities' => $app->request->get('search_non_active_entities', false),
            'searchLeafs' => $app->request->get('search_leafs', false),
            'orderBy' => $orderBy = $app->request->get('order_by', false),
            'orderValue' => $orderValue = $app->request->get('order_value', 'asc')
        ]);

        $pageNum = (int)$app->request->get('page', 1);
        $pageSize = (int)$app->request->get('size', 20);

        /** @var CategoryManager $src */
        $src = $app->managers->categories->clear();

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

        $manager = $app->managers->categoriesToEntities->clear();

        $categoryIds = [];

        if ($content->searchLeafs) {
            $categoryIds[] = $app->managers->categories->getLeafsIds();
        }

        if ($content->searchWithEntities) {
            $categoryIds[] = $manager->getCategoryList();
        }

        if ($content->searchWithNonActiveEntities) {
            $categoryIds[] = $manager->getCategoryListWithNonActiveItems();
        }

        $empty = false;

        if ($categoryIds) {
            $srcWhere['category_id'] = (count($categoryIds) > 1) ? array_intersect(...$categoryIds) : $categoryIds[0];
            $empty = count($srcWhere['category_id']) == 0;
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
            'items' => $categories = $empty ? [] : $src->getObjects(true),
            'itemEntities' => $app->managers->categoriesToEntities->getItemsGroupByCategories(['category_id' => array_keys($categories)]),
            'itemItems' => $app->managers->items->getFirstItemsFromEachCategory(['category_id' => array_keys($categories)], 5),
            'categoryPicker' => $app->managers->categories->makeTagPicker(null, false, [], $view),
            'tagsPicker' => $app->managers->tags->makeTagPicker(null, true, [], $view),
            'pager' => $app->views->pager([
                'link' => $app->router->makeLink('admin', array_merge($app->request->getGetParams(), [
                    'action' => 'category-fixes',
                    'page' => '{page}'
                ])),
                'total' => $empty ? 0 : $src->getTotal(),
                'size' => $pageSize,
                'page' => $pageNum,
                'per_set' => 5,
                'param' => 'page'
            ], $view)
        ]);

        $app->response->setHTML(200, $view);
    }
}