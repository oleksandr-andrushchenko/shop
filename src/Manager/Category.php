<?php

namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Helper;
use SNOWGIRL_SHOP\Entity\Category as CategoryEntity;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Category\Alias;
use SNOWGIRL_SHOP\Manager\Item\Attr;
use Throwable;

/**
 * Class Category
 *
 * @property CategoryEntity $entity
 * @method Category clear()
 * @method Category setWhat($what)
 * @method Category setWhere($where)
 * @method CategoryEntity[] populateList($id)
 * @method CategoryEntity[] findAll()
 * @method CategoryEntity[] getObjects($idAsKeyOrKey = null)
 * @method CategoryEntity getObject()
 * @method CategoryEntity find($id)
 * @method Alias getAliasManager()
 * @package SNOWGIRL_SHOP\Manager
 */
class Category extends Attr
{
    protected function onInsert(Entity $entity)
    {
        /** @var CategoryEntity $entity */

        $output = parent::onInsert($entity);

        if ($entity->getParentCategoryId() && !$entity->issetAttr('is_leaf')) {
            $entity->setIsLeaf(true);
        }

        return $output;
    }

    /**
     * @param Entity $entity
     * @return bool
     * @throws Throwable
     */
    protected function onUpdated(Entity $entity)
    {
        /** @var CategoryEntity $entity */

        $output = parent::onUpdated($entity);

//        $output = $output && $this->syncIsLeafColumn();

        if ($entity->isAttrChanged('parent_category_id')) {
            if ($prevParentId = $entity->getPrevAttr('parent_category_id')) {
                if ($prevParent = $this->find($prevParentId)) {
                    $prevParent->setIsLeaf(0 == $this->clear()
                            ->setWhere(['parent_category_id' => $prevParent->getId()])
                            ->getCount());

                    $this->updateOne($prevParent);
                } else {
                    throw new Exception('can\'t find old parent object');
                }
            }

            if ($newParentId = $entity->getParentCategoryId()) {
                if ($newParent = $this->find($newParentId)) {
                    $newParent->setIsLeaf(false);

                    $this->updateOne($newParent);
                } else {
                    throw new Exception('can\'t find new parent object');
                }
            }
        }

        return $output;
    }

    /**
     * @param Entity $entity
     * @return bool
     * @throws Exception
     */
    protected function onDelete(Entity $entity)
    {
        /** @var CategoryEntity $entity */

        $output = parent::onDelete($entity);

        if ($this->clear()->setWhere(['parent_category_id' => $entity->getId()])->getCount()) {
            throw new Exception('category has children');
        }

        if ($this->app->managers->items->clear()->setWhere(['category_id' => $entity->getId()])->getCount()) {
            throw new Exception('there are items with this category');
        }

//        if ($this->app->managers->catalog->clear()->getObjectByParams(['category_id' => $entity->getId()])) {
//            throw new Exception('there are catalog pages with this category');
//        }

        return $output;
    }

    /**
     * @param Entity $entity
     * @return bool
     * @throws \Exception
     */
    protected function onDeleted(Entity $entity)
    {
        /** @var CategoryEntity $entity */
        $output = parent::onDeleted($entity);

//        $output = $output && $this->syncIsLeafColumn();

        if ($parentCategory = $this->getParentCategory($entity)) {
            $parentCategory->setIsLeaf($this->clear()
                ->setWhere(['parent_category_id' => $parentCategory->getId()])
                ->getCount());

            $this->updateOne($parentCategory);
        }

        $output = $output && $this->deleteTreeCache();
        $output = $output && $this->deleteCache($entity);
        return $output;
    }

    public function syncChildren()
    {
        $this->app->managers->categoriesToChildren->createTableAndFill();
        return true;
    }

    /**
     * @param \Closure|null $changedCallback
     * @return bool
     */
    public function syncIsLeafColumn(\Closure $changedCallback = null)
    {
        $this->app->managers->categoriesToChildren->createTableAndFill();

        $counts = $this->app->managers->categoriesToChildren
            ->setGroups('category_id')
            ->getCount();

        foreach ($this->clear()->getObjects() as $category) {
            $categoryId = $category->getId();
            $category->setIsLeaf(!isset($counts[$categoryId]) || $counts[$categoryId] < 2);

            if ($category->isAttrChanged('is_leaf')) {
                $this->updateOne($category);
//                $this->updateMany(['is_leaf' => $category->getIsLeaf()], [
//                    'category_id' => $categoryId
//                ]);
//                $this->deleteCache($category);
                $changedCallback && $changedCallback($category);
            }
        }

        return true;
    }

    public function syncTree()
    {
        $this->syncChildren();
        $this->syncIsLeafColumn();
        return true;
    }

    protected $treeCache;

    /**
     * Returns tree data:
     * data[0] = [id => parent id]
     * data[1] = leaf_id[]
     *
     * @return array
     */
    protected function getTreeCache()
    {
        if (null == $this->treeCache) {
            $tmp = [];

            foreach ($this->findAll() as $id => $category) {
                $tmp[$id] = $category->getParentCategoryId();
            }

            $this->treeCache = [$tmp, array_diff(array_keys($tmp), $tmp)];
        }

        return $this->treeCache;
    }

    public function getRawParents()
    {
        return $this->getTreeCache()[0];
    }

    public function getLeafsIds()
    {
        return $this->getTreeCache()[1];
    }

    public function deleteTreeCache()
    {
        $output = $this->getCache()->delete($this->getAllIDsCacheKey());
        $this->treeCache = null;
        return $output;
    }

    /**
     * @param string|int|Category $item
     * @param bool|false $useSelf
     * @return CategoryEntity[]
     * @throws Exception
     */
    public function getChainedParents($item, $useSelf = false)
    {
        $output = [];

        $item = $this->normalizeCategory($item);

        $ids = $this->getParentsId($item->getId());
        $ids = array_reverse($ids);

        foreach ($ids as $id) {
            if ($category = $this->find($id)) {
                $output[$id] = $category;
            } else {
                $this->app->container->logger->makeForce('Категория[' . $id . '] не была найдена', Logger::TYPE_ERROR);
                $this->app->container->logger->makeForce(debug_backtrace(), Logger::TYPE_ERROR);
//                $this->getCache()->flush();
            }
        }

        if ($useSelf) {
            $output[$item->getId()] = $item;
        }

        return $output;
    }

    public function getRootId()
    {
        return $this->getDirectChildrenId(null);
    }

    public function getParentsIdFor($id, &$ids = [])
    {
        $ps = $this->getRawParents();

        if (!isset($ps[$id])) {
            return $ids;
        }

        $ids[] = $tmp = $ps[$id];

        return $this->getParentsIdFor($tmp, $ids);
    }

    /**
     * @param $category
     * @return array
     * @throws Exception
     */
    public function getParentsId($category)
    {
        return $this->getParentsIdFor($this->normalizeCategory($category, true));
    }

    /**
     * @param $categoryId
     * @return array
     */
    public function getDirectChildrenId($categoryId)
    {
        if (in_array($categoryId, $this->getLeafsIds())) {
            return [];
        }

        return array_keys(array_filter($this->getRawParents(), function ($parentId) use ($categoryId) {
            return $categoryId == $parentId;
        }));
    }

    /**
     * Returns given id + all children id
     *
     * @param $id
     * @return array
     */
    public function getChildrenIdFor($id)
    {
        $tmp = [];

        foreach ((array) $id as $id2) {
            $tmp[] = $id2;
            $tmp = array_merge($this->getChildrenIdFor($this->getDirectChildrenId($id2)), $tmp);
        }

        return $tmp;
    }

    public function getChildrenIdFor2($id)
    {
        $tmp = [];

        foreach ((array) $id as $id2) {
            $tmp[] = $id2;
            $tmp = array_merge($this->getChildrenIdFor2(array_keys(array_filter($this->getRawParents(), function ($parentId) use ($id2) {
                return $id2 == $parentId;
            }))), $tmp);
        }

        return $tmp;
    }

    protected $itemsCounts;

    protected function makeItemsCounts(URI $uri)
    {
        if (null === $this->itemsCounts) {
            $this->itemsCounts = $this->clear()->getFiltersCountsByUri($uri);
        }

        return $this->itemsCounts;
    }

    public function getItemsCount(CategoryEntity $entity)
    {
        $this->makeItemsCounts(new URI());
        return $this->getCountFor($entity->getId());
    }

    protected function getMenuItemsCounts($activeId, array $ids = null)
    {
        $tmp = [];

        if (null === $ids) {
            $ids = $this->getRootId();
        }

        foreach ($ids as $id) {
            if ($count = $this->getCountFor($id)) {
                $tmp[$id] = $count;

                foreach ($this->getMenuItemsCounts($activeId, $this->getDirectChildrenId($id)) as $k => $v) {
                    $tmp[$k] = $v;
                }
            }
        }

        return $tmp;
    }

    public function getCountCustom(Entity $entity)
    {
        return $this->getCountFor($entity->getId());
    }

    public function getCountFor($id)
    {
        $output = 0;

        foreach ($this->getChildrenIdFor($id) as $id) {
            $output += $this->itemsCounts[$id] ?? 0;
        }

        return $output;
    }

    /**
     * @todo use for find or findMany...
     * @var CategoryEntity[]
     */
    protected $menuItems;

    /**
     * Keep types only (in links)
     *
     * @param URI|null $uri - if passed - make open-door tree, if not - admin tree
     * @return null|string
     */
    public function makeTreeHtml(URI $uri = null)
    {
        //@todo find out what's going on here
//        $this->makeItemsCounts(new URI);

//        $params = $uri->getParamsByNames([URI::SPORT, URI::SIZE_PLUS]);
//        $params = $uri->getParamsByNames(URI::TYPE_PARAMS);
        $params = [];

        $this->makeItemsCounts(new URI($params));

        $counts = $this->getMenuItemsCounts($uri->get('category_id'));

        $categories = $this->findMany(array_keys($counts));
//        $this->sortByRating($categories);

        $this->menuItems = [];

        foreach ($categories as $category) {
            $id = $category->getId();
            $this->menuItems[$id] = $category->setRawVar('count', $counts[$id]);
        }

        return $this->makeOpenDoorTreeHtml($uri->get('category_id'), $params);
    }

    /**
     * @todo sort by ratings (aggregated!)...
     * @param            $activeId
     * @param array $params
     * @param array|null $ids
     * @return string
     */
    protected function makeOpenDoorTreeHtml($activeId, array $params = [], array $ids = null)
    {
        if ($isRoot = (null === $ids)) {
            $ids = $this->getRootId();
        }

        if (!$ids) {
            return '';
        }

        $tmp = [];

        $tmp[] = '<ul' . ($isRoot ? ' id="category-tree"' : '') . '>';

        /** @var CategoryEntity[] $categories */
        $categories = [];

        foreach ($ids as $id) {
            //filter empty (no items) categories
            if (isset($this->menuItems[$id])) {
                $categories[$id] = $this->menuItems[$id];
            }
        }

        $this->sortByRating($categories);

        foreach ($categories as $category) {
            $id = $category->getId();

            $isActive = $activeId && $activeId == $id;

            $isActiveGroup = $isRoot || ($activeId && (
                        // show active
                        $activeId == $id ||
                        // show parents
                        in_array($id, $pa = $this->getParentsIdFor($activeId)) ||
                        // show direct children
                        in_array($id, $this->getDirectChildrenId($activeId)) ||
                        // show those who's parent is in active state already
                        in_array($category->getParentCategoryId(), $pa)
                    ));

            $class = [];

            if ($isActive) {
                $class[] = 'active';
            }

            if ($isActiveGroup) {
                $class[] = 'active-group';
            }

            $tmp[] = '<li data-id="' . $id . '" data-parent-id="' . $category->getParentCategoryId() . '"' . ($class ? (' class="' . implode(' ', $class) . '"') : '') . '>';

            $tmp[] = '<div class="item-wrap">';

            if ($isActive) {
                $tmp[] = '<span class="item nav-item">';
                $tmp[] = $category->getBreadcrumb() ?: $category->getName();
                $tmp[] = '</span>';
            } else {
                $tmp[] = '<a class="item nav-item" href="' . (new URI(array_merge($params, ['category_id' => $id])))
                        ->output(URI::OUTPUT_DEFINED, false, $isNoFollow) . '" ' . ($isNoFollow ? 'rel="nofollow"' : '') . '>';
                $tmp[] = $category->getBreadcrumb() ?: $category->getName();
                $tmp[] = '</a>';
            }

            $tmp[] = ' <span class="count">' . Helper::makeNiceNumber($category->getRawVar('count')) . '</span>';

            $tmp[] = '</div>';

            $tmp[] = $this->makeOpenDoorTreeHtml($activeId, $params, $this->getDirectChildrenId($id));
            $tmp[] = '</li>';
        }

        $tmp[] = '</ul>';

        if (2 == count($tmp)) {
            return '';
        }

        return implode('', $tmp);
    }

    /**
     * @param            $category
     * @param bool|false $id
     * @return CategoryEntity|int
     * @throws Exception
     */
    protected function normalizeCategory($category, $id = false)
    {
        if (is_numeric($category) || is_int($category)) {
            if ($id) {
                return (int) $category;
            }

            $category = $this->find($category);
        }

        if ($category instanceof CategoryEntity) {
            if ($id) {
                return $category->getId();
            }

            return $category;
        }

        if (!$id && !$category instanceof CategoryEntity) {
            throw new Exception('Invalid CategoryEntity object');
        }

        return $category;
    }

    public function getLevel($category)
    {
        return count($this->getParentsId($this->normalizeCategory($category, true)));
    }

    public static function getChildren($category)
    {

    }

    /**
     * @param CategoryEntity $category
     * @return Entity|CategoryEntity|null
     */
    public function getParentCategory(CategoryEntity $category)
    {
        return $this->getLinked($category, 'parent_category_id');
    }


    /**
     * @param CategoryEntity[] $categories
     * @return array
     */
    public function sortByUriLength(array &$categories)
    {
        usort($categories, function ($a, $b) {
            /** @var CategoryEntity $a */
            /** @var CategoryEntity $b */
            $a = strlen($a->getUri());
            $b = strlen($b->getUri());

            if ($a == $b) {
                return 0;
            }

            return ($a < $b) ? 1 : -1;
        });

        return $categories;
    }

    /**
     * @param CategoryEntity[] $categories
     * @return CategoryEntity[]
     */
    public function sortByRating(array &$categories)
    {
        usort($categories, function ($a, $b) {
            /** @var CategoryEntity $a */
            /** @var CategoryEntity $b */
            $a = $a->getRating();
            $b = $b->getRating();

            if ($a == $b) {
                return 0;
            }

            return ($a < $b) ? 1 : -1;
        });

        return $categories;
    }

    /**
     * @return array|CategoryEntity[]
     * @throws \Exception
     */
    public function getLeafObjects()
    {
        return array_filter($this->findAll(), function ($category) {
            return $this->isLeaf($category);
        });
    }

    /**
     * @return array|CategoryEntity[]
     * @throws \Exception
     */
    public function getNonLeafObjects()
    {
        return array_filter($this->findAll(), function ($category) {
            return !$this->isLeaf($category);
        });
    }

    /**
     * @param $category
     * @return array|CategoryEntity[]
     * @throws Exception
     */
    public function getDirectChildrenObjects($category)
    {
        $categories = $this->findAll();

        return array_map(function ($id) use ($categories) {
            return $categories[$id];
        }, $this->getDirectChildrenId($this->normalizeCategory($category, true)));
    }

    /**
     * @return array|CategoryEntity[]
     * @throws Exception
     */
    public function getRootObjects()
    {
        return $this->getDirectChildrenObjects(null);
    }

    /**
     * @param CategoryEntity $category
     * @return bool
     */
    public function isLeaf($category)
    {
        return $category->isLeaf();
    }

    public function isLeafById($id)
    {
        return in_array($id, $this->getLeafsIds());
    }

    /**
     * @param $category
     * @return bool
     * @throws Exception
     */
    public function isRoot($category)
    {
        return null === $this->normalizeCategory($category)->getParentCategoryId();
    }
}