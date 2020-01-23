<?php

namespace SNOWGIRL_SHOP\Manager\Category;

use SNOWGIRL_CORE\App;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Entity\Category as CategoryEntity;

/**
 * Class Child
 *
 * @property App $app
 * @method static Child factory($app)
 * @package SNOWGIRL_SHOP\Manager\Category
 */
class Child extends Manager
{
    protected static $createdAndFilled;

    public function createTableAndFill()
    {
        if (true === self::$createdAndFilled) {
            return true;
        }

        $db = $this->app->services->rdbms;
        $table = $this->entity->getTable();

        $db->dropTable($table);

        $db->req(implode(' ', [
            'CREATE ' . 'TABLE ' . $db->quote($table) . ' (',
            $db->quote(CategoryEntity::getPk()) . ' int(11) NOT NULL,',
            $db->quote('child_category_id') . ' int(11) NOT NULL',
            ') ENGINE=MyISAM DEFAULT CHARSET=utf8'
        ]));

        $categories = $this->app->managers->categories->clear();

        $categories->deleteTreeCache();

        $query = implode(' ', [
            'INSERT' . ' INTO ' . $db->quote($table),
            '(' . $db->quote(CategoryEntity::getPk()) . ', ' . $db->quote('child_category_id') . ')',
            'VALUES'
        ]);

        $tmp = [];

        foreach ($categories->getObjects() as $category) {
            /** @var CategoryEntity $category */
            foreach ($categories->getChildrenIdFor2($category->getId()) as $id) {
                $tmp[] = '(' . $category->getId() . ', ' . $id . ')';
            }
        }

        $query .= implode(', ', $tmp);

        $db->req($query);

        return self::$createdAndFilled = true;
    }

    public function getGroupedArrays(bool $doNotIncludeSelf = false): array
    {
        $output = [];

        foreach ($this->getArrays() as $row) {
            if (!isset($output[$row['category_id']])) {
                $output[$row['category_id']] = [];
            }

            if ($doNotIncludeSelf) {
                if ($row['category_id'] != $row['child_category_id']) {
                    $output[$row['category_id']][] = $row['child_category_id'];
                }
            } else {
                $output[$row['category_id']][] = $row['child_category_id'];
            }
        }

        return $output;
    }
}