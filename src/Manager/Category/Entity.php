<?php

namespace SNOWGIRL_SHOP\Manager\Category;

use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_CORE\App;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_SHOP\Entity\Category as CategoryEntity;
use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Entity\Category\Entity as CategoryEntityEntity;
use SNOWGIRL_SHOP\Entity\Item as ItemEntity;
use SNOWGIRL_SHOP\Item\FixWhere;

/**
 * Class Entity
 *
 * @property App app
 * @method static Entity factory($app)
 * @method Entity clear()
 * @package SNOWGIRL_SHOP\Manager\Category
 */
class Entity extends Manager
{
    const INCLUDE_ENTITY_COUNT_FROM = 50;
    const WILDCARD_SYMBOL = '*';

    protected static $createdAndFilled;

    /**
     * @param bool $force
     *
     * @return bool
     */
    public function createTableAndFill($force = false)
    {
        if (self::$createdAndFilled && !$force) {
            return true;
        }

        $db = $this->app->services->rdbms;
        $table = $this->entity->getTable();
        $pk = $this->entity->getPk();

        $db->req(implode(' ', [
                'CREATE ' . 'TABLE IF NOT EXISTS ' . $db->quote($table) . ' (',
                $db->quote($pk) . ' SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,',
                $db->quote('category_id') . ' SMALLINT UNSIGNED NOT NULL DEFAULT 0,',
                $db->quote('value') . ' TINYTEXT,',
                $db->quote('value_hash') . ' CHAR(32) NOT NULL,',
                $db->quote('count') . ' SMALLINT UNSIGNED NOT NULL DEFAULT 0,',
                $db->quote('is_active') . ' TINYINT UNSIGNED NOT NULL DEFAULT 0,',
                'PRIMARY KEY (' . $db->quote($pk) . '),',
                'UNIQUE KEY (' . $db->quote('category_id') . ', ' . $db->quote('value_hash') . ') )',
                'ENGINE=MyISAM DEFAULT CHARSET=utf8'
            ])
        );

        $this->deleteMany(['is_active' => 0]);

        $query = new Query(['params' => [self::INCLUDE_ENTITY_COUNT_FROM]]);
        $query->text = implode(' ', [
            'INSERT' . ' INTO ' . $db->quote($table),
            '(' . $db->quote('category_id') . ', ' . $db->quote('value') . ', ' . $db->quote('value_hash') . ', ' . $db->quote('count') . ')',
            '(SELECT ' . $db->quote('category_id') . ', ' . $db->quote('entity') . ', MD5(' . $db->quote('entity') . '), COUNT(*) AS ' . $db->quote('cnt'),
            'FROM ' . $db->quote(ItemEntity::getTable()),
            'WHERE ' . $db->quote('entity') . ' <> \'\'',
            'GROUP BY ' . $db->quote('entity') . ', ' . $db->quote('category_id'),
            'HAVING ' . $db->quote('cnt') . ' > ?)',
            'ON DUPLICATE KEY UPDATE ' . $db->quote('count') . ' = VALUES(' . $db->quote('count') . ')'
        ]);

        $db->req($query);

        $this->deleteMany(new Expr($db->quote('category_id') . ' NOT IN (SELECT ' . $db->quote(Category::getPk()) . ' FROM ' . $db->quote(Category::getTable()) . ')'));

        $this->deleteMany(['is_active' => 0, 'count' => 0]);

        return self::$createdAndFilled = true;
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
    protected function getActiveNonEmptyObjects()
    {
        return $this->clear()
            ->setWhere([
                'is_active' => 1,
                new Expr($this->app->services->rdbms->quote('count') . ' > 0')
            ])
            ->getObjects();
    }

    /**
     * @todo add force index...
     *
     * @param       $function
     * @param       $categoryId
     * @param array $where
     */
    protected function updateItemsCategory($function, $categoryId, array $where)
    {
        $where = Arrays::sortByKeysArray($where, ['category_id', 'vendor_id', 'import_source_id', 'created_at', 'partner_updated_at', 'entity', 'name']);

        if ($aff = $this->app->managers->items->updateMany(['category_id' => $categoryId], $where)) {
            $this->app->services->logger->make(implode(' - ', [
                    'method=' . $function,
                    'cat=' . $categoryId,
                    'where=' . json_encode($where)
                ]) . ' : aff=' . $aff);
        }
    }

    /**
     * @param FixWhere|null $fixWhere
     *
     * @return bool
     */
    public function updateByParentsAndEntities(FixWhere $fixWhere = null)
    {
        $this->createTableAndFill();

        $where = $fixWhere->get();
        $categories = $this->app->managers->categories->clear();

        foreach ($this->getActiveNonEmptyObjects() as $entity) {
            if ($categories->isLeafById($entity->getCategoryId())) {
                if ($parentCategoryId = $categories->getParentsId($entity->getCategoryId())) {
                    $this->updateItemsCategory(__FUNCTION__, $entity->getCategoryId(), array_merge($where, [
                        'category_id' => $parentCategoryId,
                        'entity' => $entity->getValue()
                    ]));
                }
            }
        }

        return true;
    }

    /**
     * @param FixWhere|null $fixWhere
     *
     * @return bool
     */
    public function updateByParentsAndNamesLikeCategories(FixWhere $fixWhere = null)
    {
        $this->createTableAndFill();

        $where = $fixWhere ? $fixWhere->get() : [];

        foreach ($this->app->managers->categories->getLeafObjects() as $category) {
            if ($parentCategoryId = $this->app->managers->categories->getParentsId($category)) {
                $this->updateItemsCategory(__FUNCTION__, $category->getId(), array_merge($where, [
                    'category_id' => $parentCategoryId,
                    'entity' => '',
                    'name' => new Expr($this->app->services->rdbms->quote('name') . ' LIKE ?', $category->getName() . '%')
                ]));
            }
        }

        return true;
    }

    /**
     * @param FixWhere|null $fixWhere
     *
     * @return bool
     */
    public function updateByEntities(FixWhere $fixWhere = null)
    {
        $this->createTableAndFill();

        $where = $fixWhere ? $fixWhere->get() : [];

        foreach ($this->getActiveNonEmptyObjects() as $entity) {
            if ($this->app->managers->categories->isLeafById($entity->getCategoryId())) {
                $this->updateItemsCategory(__FUNCTION__, $entity->getCategoryId(), array_merge($where, [
                    'entity' => $entity->getValue()
                ]));
            }
        }

        return true;
    }

    /**
     * @param FixWhere|null $fixWhere
     *
     * @return bool
     */
    public function updateByNamesLikeEntities(FixWhere $fixWhere = null)
    {
        $this->createTableAndFill();

        $where = $fixWhere ? $fixWhere->get() : [];

        foreach ($this->getActiveNonEmptyObjects() as $entity) {
            if ($this->app->managers->categories->isLeafById($entity->getCategoryId())) {
                $this->updateItemsCategory(__FUNCTION__, $entity->getCategoryId(), array_merge($where, [
                    'entity' => '',
                    'name' => new Expr($this->app->services->rdbms->quote('name') . ' LIKE ?', $entity->getValue() . '%')
                ]));
            }
        }

        return true;
    }

    /**
     * @param FixWhere|null $fixWhere
     *
     * @return bool
     */
    public function updateByEntitiesLikeEntities(FixWhere $fixWhere = null)
    {
        $this->createTableAndFill();

        $where = $fixWhere ? $fixWhere->get() : [];

        foreach ($this->getActiveNonEmptyObjects() as $entity) {
            if ($this->app->managers->categories->isLeafById($entity->getCategoryId())) {
                $this->updateItemsCategory(__FUNCTION__, $entity->getCategoryId(), array_merge($where, [
                    'entity' => new Expr($this->app->services->rdbms->quote('entity') . ' LIKE ?', $entity->getValue() . '%')
                ]));
            }
        }

        return true;
    }
}