<?php

namespace SNOWGIRL_SHOP\Manager\Category;

use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_SHOP\Entity\Category as CategoryEntity;
use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Entity\Category\Entity as CategoryEntityEntity;
use SNOWGIRL_SHOP\Entity\Item as ItemEntity;
use SNOWGIRL_SHOP\Item\FixWhere;
use SNOWGIRL_SHOP\App\Web;
use SNOWGIRL_SHOP\App\Console;

/**
 * Class Entity
 *
 * @property Web|Console app
 * @method static Entity factory($app)
 * @method Entity clear()
 * @package SNOWGIRL_SHOP\Manager\Category
 */
class Entity extends Manager
{
    const INCLUDE_ENTITY_COUNT_FROM = 50;
    const WILDCARD_SYMBOL = '*';

    protected function onInsert(\SNOWGIRL_CORE\Entity $entity)
    {
        /** @var CategoryEntityEntity $entity */

        $output = parent::onInsert($entity);

        $entity->setEntityHash($entity->normalizeHash($entity->getEntity()));

        return $output;
    }

    protected function onUpdate(\SNOWGIRL_CORE\Entity $entity)
    {
        /** @var CategoryEntityEntity $entity */

        $output = parent::onUpdate($entity);

        if ($entity->isAttrChanged('entity')) {
            $entity->setEntityHash($entity->normalizeHash($entity->getEntity()));
        }

        return $output;
    }

    protected static $generated;

    /**
     * @param bool $force
     *
     * @return bool
     */
    public function generate($force = false)
    {
        if (self::$generated && !$force) {
            return true;
        }

        $db = $this->getStorage();
        $table = $this->getEntity()->getTable();
        $pk = $this->getEntity()->getPk();

        $categoryPkQuotted = $db->quote($this->app->managers->categories->getEntity()->getPk());
        $categoryTableQuotted = $db->quote($this->app->managers->categories->getEntity()->getTable());

        $this->deleteMany(['is_active' => 0]);

        $query = new Query(['params' => [self::INCLUDE_ENTITY_COUNT_FROM]]);
        $query->text = implode(' ', [
            'INSERT' . ' INTO ' . $db->quote($table),
            '(' . $db->quote('category_id') . ', ' . $db->quote('entity') . ', ' . $db->quote('entity_hash') . ', ' . $db->quote('count') . ')',
            '(SELECT ' . $categoryPkQuotted . ', ' . $db->quote('entity') . ', MD5(' . $db->quote('entity') . '), COUNT(*) AS ' . $db->quote('cnt'),
            'FROM ' . $db->quote($this->app->managers->items->getEntity()->getTable()),
            'WHERE ' . $db->quote('entity') . ' <> \'\'',
            'GROUP BY ' . $db->quote('entity') . ', ' . $categoryPkQuotted,
            'HAVING ' . $db->quote('cnt') . ' > ?)',
            'ON DUPLICATE KEY UPDATE ' . $db->quote('count') . ' = VALUES(' . $db->quote('count') . ')'
        ]);

        $db->req($query);

        $this->deleteMany(new Expr($categoryPkQuotted . ' NOT IN (SELECT ' . $categoryPkQuotted . ' FROM ' . $categoryTableQuotted . ')'));
        $this->deleteMany(['is_active' => 0, 'count' => 0]);

        return self::$generated = true;
    }

    public function getItemsGroupByCategories(array $where = null)
    {
        $output = [];

        /** @var CategoryEntityEntity $item */
        foreach ($this->clear()->setWhere($where)->getObjects() as $item) {
            $id = $item->getCategoryId();

            if (!isset($output[$id])) {
                $output[$id] = [];
            }

            $output[$id][] = $item;
        }

        return $output;
    }

    public function getCategoryList()
    {
        return $this->clear()->getList('category_id');
    }

    public function getCategoryListWithNonActiveItems()
    {
        return $this->clear()->setWhere(['is_active' => 0])->getList('category_id');
    }

    /**
     * @param CategoryEntityEntity $entity
     *
     * @return CategoryEntity
     */
    public function getCategory(CategoryEntityEntity $entity)
    {
        return $this->getLinked($entity, 'category_id');
    }

    /**
     * @return CategoryEntityEntity[]
     */
    protected function getActiveObjects(array $where = [])
    {
        return $this->clear()->setWhere(array_merge(['is_active' => 1], $where))->getObjects();
    }

    protected function updateItemsCategory($function, $categoryId, array $where): int
    {
        $aff = $this->app->managers->items->updateMany(['category_id' => $categoryId], $where);

        $this->app->services->logger->make(implode(' - ', [
                'method=' . $function,
                'cat=' . $categoryId,
                'where=' . json_encode($where)
            ]) . ' : aff=' . $aff);

        return $aff;
    }

    protected function getStopWordsWhere(string $stopWords = null): array
    {
        if (!$stopWords) {
            return [];
        }

        $params = array_map(function ($stopWord) {
            return '%' . trim($stopWord) . '%';
        }, explode(',', $stopWords));

        $query = $this->app->storage->mysql->quote('name') . ' NOT LIKE ?';
        $query = implode(' OR ', array_fill(0, count($params), $query));
//        $query = '(' . $query . ')';

        array_unshift($params, $query);

        return [new Expr(...$params)];
    }

    public function updateByParentsAndEntities(FixWhere $fixWhere = null): int
    {
        $this->generate();

        $aff = 0;

        $where = $fixWhere ? $fixWhere->get() : [];

        foreach ($this->getActiveObjects() as $entity) {
            if ($this->app->managers->categories->isLeafById($entity->getCategoryId())) {
                if ($parentCategoryId = $this->app->managers->categories->getParentsId($entity->getCategoryId())) {
                    $aff += $this->updateItemsCategory(__FUNCTION__, $entity->getCategoryId(), array_merge($where, [
                        'category_id' => $parentCategoryId,
                        'entity' => $entity->getEntity()
                    ], $this->getStopWordsWhere($entity->getStopWords())));
                }
            }
        }

        return $aff;
    }

    public function updateByParentsAndNamesLikeCategories(FixWhere $fixWhere = null): int
    {
        $this->generate();

        $aff = 0;

        $where = $fixWhere ? $fixWhere->get() : [];

        foreach ($this->app->managers->categories->getLeafObjects() as $category) {
            if ($parentCategoryId = $this->app->managers->categories->getParentsId($category)) {
                $aff += $this->updateItemsCategory(__FUNCTION__, $category->getId(), array_merge($where, [
                    'category_id' => $parentCategoryId,
                    'entity' => '',
                    new Expr($this->app->storage->mysql->quote('name') . ' LIKE ?', $category->getName() . '%')
                ]));
            }
        }

        return $aff;
    }

    public function updateByParentsAndNamesLikeEntities(FixWhere $fixWhere = null): int
    {
        $this->generate();

        $aff = 0;

        $where = $fixWhere ? $fixWhere->get() : [];

        foreach ($this->getActiveObjects() as $entity) {
            if ($this->app->managers->categories->isLeafById($entity->getCategoryId())) {
                if ($parentCategoryId = $this->app->managers->categories->getParentsId($entity->getCategoryId())) {
                    $aff += $this->updateItemsCategory(__FUNCTION__, $entity->getCategoryId(), array_merge($where, [
                        'category_id' => $parentCategoryId,
                        'entity' => '',
                        new Expr($this->app->storage->mysql->quote('name') . ' LIKE ?', $entity->getEntity() . '%')
                    ], $this->getStopWordsWhere($entity->getStopWords())));
                }
            }
        }

        return $aff;
    }

    public function updateByEntities(FixWhere $fixWhere = null): int
    {
        $this->generate();

        $aff = 0;

        $where = $fixWhere ? $fixWhere->get() : [];

        foreach ($this->getActiveObjects() as $entity) {
            if ($this->app->managers->categories->isLeafById($entity->getCategoryId())) {
                $aff += $this->updateItemsCategory(__FUNCTION__, $entity->getCategoryId(), array_merge($where, [
                    'entity' => $entity->getEntity()
                ], $this->getStopWordsWhere($entity->getStopWords())));
            }
        }

        return $aff;
    }

    public function updateByEntitiesLikeEntities(FixWhere $fixWhere = null): int
    {
        $this->generate();

        $aff = 0;

        $where = $fixWhere ? $fixWhere->get() : [];

        foreach ($this->getActiveObjects() as $entity) {
            if ($this->app->managers->categories->isLeafById($entity->getCategoryId())) {
                $aff += $this->updateItemsCategory(__FUNCTION__, $entity->getCategoryId(), array_merge($where, [
                    new Expr($this->app->storage->mysql->quote('entity') . ' LIKE ?', $entity->getEntity() . '%')
                ], $this->getStopWordsWhere($entity->getStopWords())));
            }
        }

        return $aff;
    }
}