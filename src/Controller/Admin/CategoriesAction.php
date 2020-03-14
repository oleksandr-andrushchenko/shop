<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Category\Alias;
use SNOWGIRL_SHOP\Manager\Category;
use SNOWGIRL_SHOP\RBAC;

class CategoriesAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_CATEGORIES_PAGE);

        $manager = $app->managers->categories;
        $categories = $manager->findMany(array_keys($manager->getRawParents()));
        $aliases = $app->managers->categories->getAliasManager()->getGroupedByCategoryObjects();

        $view = $app->views->getLayout(true);

        $view->setContentByTemplate('@shop/admin/categories.phtml', [
            'tree' => $this->makeAdminTreeHtml($manager, $categories, $aliases)
        ]);

        $app->response->setHTML(200, $view);
    }

    protected function makeAdminTreeHtml(Category $manager, array $categories, array $aliases, array $ids = null): string
    {
        if ($isRoot = (null === $ids)) {
            $ids = $manager->getRootId();
        }

        if (!$ids) {
            return '';
        }

        $tmp = [];

        if ($isRoot) {
            $tmp[] = '<div class="dd" id="category-tree">';
        }

        $tmp[] = '<ol class="dd-list">';

        foreach ($ids as $id) {
            $tmp[] = '<li class="dd-item dd3-item" data-id="' . $id . '">';
            $tmp[] = '<div class="dd-handle dd3-handle" title="Переместить"></div>';
            $tmp[] = '<div class="dd3-content">' . $id . ' &mdash; ' . implode('<br>', [
                    $categories[$id]->getBreadcrumb() . ': ' . $categories[$id]->getName(),
                    implode('<br>', array_map(function (Alias $alias) {
                        return $alias->get('breadcrumb') . ': ' .$alias->get('name');
                    }, $aliases[$id] ?? []))
                ]) . '</div>';
            $tmp[] = $this->makeAdminTreeHtml($manager, $categories, $aliases, $manager->getDirectChildrenId($id));
            $tmp[] = '</li>';
        }

        $tmp[] = '</ol>';

        if ($isRoot) {
            $tmp[] = '</div>';
        }

        return implode('', $tmp);
    }
}