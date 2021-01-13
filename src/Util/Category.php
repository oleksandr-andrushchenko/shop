<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Entity\Redirect;
use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Mysql\Mysql;
use SNOWGIRL_CORE\Mysql\MysqlQueryExpression;
use SNOWGIRL_CORE\Mysql\MysqlQuery;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Entity\Category as CategoryEntity;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\Tag;
use SNOWGIRL_SHOP\Entity\Item\Tag as TagItem;
use SNOWGIRL_SHOP\Entity\Page\Catalog;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_SHOP\Catalog\URI\Manager as CatalogUriManager;
use SNOWGIRL_SHOP\Http\HttpApp;
use Throwable;

/**
 * @property HttpApp|ConsoleApp app
 */
class Category extends Util
{
    /**
     * @todo fix... duplicates... (ignore param..)
     * @param string $delimiter
     * @param null $error
     * @return bool
     * @throws Entity\EntityException
     */
    public function doBuildTreeByNames($delimiter = '/', &$error = null)
    {
        /**
         *  SELECT category_id, name FROM category where ROUND((LENGTH(name) - LENGTH(REPLACE(name, "/", ""))) / LENGTH("/")) > 0 limit 1;
         * update category
         * set
         * name=replace(name,'Завивка волос/',''),
         * uri=replace(uri,'zavivka-volos-',''),
         * parent_category_id=563
         * where name like 'Завивка волос/%' and category_id not in(563);
         */

        $pk = $this->app->managers->categories->getEntity()->getPk();
        $qPk = $this->app->container->mysql->quote($pk);
        $qName = $this->app->container->mysql->quote('name');
        $qUri = $this->app->container->mysql->quote('uri');

        $ignore = [-1];

        while ($category = $this->app->managers->categories->clear()
            ->setColumns([
                $pk,
                'name'
            ])->setWhere([
                new MysqlQueryExpression('ROUND((LENGTH(' . $qName . ') - LENGTH(REPLACE(' . $qName . ', "' . $delimiter . '", ""))) / LENGTH("' . $delimiter . '")) > 0'),
                new MysqlQueryExpression($qPk . ' NOT IN (' . implode(',', $ignore) . ')')
            ])->getObject()) {
            $rawParentName = explode($delimiter, $category->getName())[0];
            $parentName = CategoryEntity::normalizeText($rawParentName);

            if (!$parent = $this->app->managers->categories->clear()->setWhere(['name' => $parentName])->getObject()) {
                $parent = (new CategoryEntity)
                    ->setName($parentName);

                $this->app->managers->categories->insertOne($parent);
            }

            $likeQuery = $rawParentName . $delimiter;

            try {
                $this->app->managers->categories->updateMany([
                    'name' => new MysqlQueryExpression('REPLACE(' . $qName . ', ?, "")', $likeQuery),
                    'uri' => new MysqlQueryExpression('REPLACE(' . $qUri . ', ?, "")', $parent->getUri() . '-'),
                    'parent_category_id' => $parent->getId()
                ], [
                    new MysqlQueryExpression($qName . ' LIKE ?', $likeQuery . '%'),
                    new MysqlQueryExpression($qPk . ' <> ?', $parent->getId())
                ], ['ignore' => true]);
            } catch (Throwable $e) {
                $error = $e->getMessage();

                if (false === strpos($e->getMessage(), 'Duplicate')) {
                    return false;
                }
            }


            $ignore[] = $category->getId();
        }

        return true;
    }

    /**
     * @return bool
     */
    public function doTransferCategoriesToTags()
    {
        $categoryToTags = [];

        $mysql = $this->app->container->mysql;

        /**
         * @return CategoryEntity[]
         */
        $makeCategories = function () use ($mysql) {
            $this->app->managers->categories->deleteTreeCache();

            /** @var CategoryEntity[] $categories */
            $categories = $this->app->managers->categories->clear()
                ->setWhere(new MysqlQueryExpression($mysql->quote('parent_category_id') . ' IS NOT NULL'))
                ->getObjects(true);

            //cache categories parents
            foreach ($categories as $category) {
                $this->app->managers->categories->getParentCategory($category);
            }

            return $categories;
        };

        /** @var CategoryEntity[] $categories */
        $categories = $makeCategories();

        /** @var Tag[] $tags */
        $tags = $this->app->managers->tags->clear()->getObjects(true);

        foreach ($categories as $categoryId => $category) {
            foreach ($tags as $tagId => $tag) {
                if (false !== strpos(mb_strtolower($category->get('name')), mb_strtolower($tag->get('name')))) {
                    if (!isset($categoryToTags[$categoryId])) {
                        $categoryToTags[$categoryId] = [];
                    }

                    $categoryToTags[$categoryId][] = $tagId;
                }
            }
        }

        $deletedCategories = [];

        foreach ($categoryToTags as $categoryId => $tagsIds) {
            $category = $categories[$categoryId];
            $parentCategory = $this->app->managers->categories->getParentCategory($category);

            if (in_array($parentCategory->getId(), $deletedCategories)) {
                $this->app->container->logger->debug('Category parent was deleted, have a look at ::delete ..skipping...');
                continue;
            }

            foreach ($tagsIds as $tagId) {
                $query = new MysqlQuery(['params' => []]);
                $query->text = implode(' ', [
                    'INSERT IGNORE INTO',
                    $mysql->quote(TagItem::getTable()),
                    '(' . $mysql->quote('item_id') . ', ' . $mysql->quote('tag_id') . ')',
                    'SELECT ' . $mysql->quote(Item::getPk()) . ', ' . $tagId,
                    $mysql->makeFromSQL(Item::getTable()),
                    $mysql->makeWhereSQL(['category_id' => $categoryId], $query->params, null, $query->placeholders)
                ]);

                $mysql->req($query);

                $this->app->managers->catalog->updateMany(['category_id' => $parentCategory->getId(), 'tag_id' => $tagId], [
                    'category_id' => $categoryId
                ], ['ignore' => true]);

                $this->app->managers->catalog->deleteMany([
                    'category_id' => $categoryId
                ]);
            }

            $this->app->managers->items->updateMany(['category_id' => $parentCategory->getId()], [
                'category_id' => $categoryId
            ], ['ignore' => true]);

            $this->app->managers->redirects->insertOne(new Redirect([
                'uri_from' => $category->getUri(),
                'uri_to' => $parentCategory->getUri()
            ]));

            $this->app->managers->redirects->updateMany(['uri_to' => $parentCategory->getUri()], [
                'uri_from' => $category->getUri()
            ], ['ignore' => true]);

            //@todo collect categories to delete... and do it after all actions...
            //@todo update parents for categories which have this category as parent.... CRITICAL


            while (true) {
                try {
                    if ($this->app->managers->categories->deleteOne($category)) {
                        $deletedCategories[] = $category->getId();
                        break;
                    } else {
                        $this->app->container->logger->debug('Can\'t delete category[' . $category->getId() . ']... skipping...');
                    }

                    $categories = $makeCategories();
                } catch (Throwable $e) {
                    $this->app->container->logger->error($e);

                    if ($e->check(CategoryEntity::getTable())) {
                        //this category has children - fix them first @todo...
                        foreach ($this->app->managers->categories->getDirectChildrenId($category->getId()) as $directChildCategoryId) {

                            if (in_array($directChildCategoryId, $deletedCategories)) {
                                $this->app->container->logger->debug('Direct Child Category deleted already... skipping...');
                                continue;
                            }

                            /** @var CategoryEntity $directChildCategory */
                            if ($directChildCategory = $this->app->managers->categories->find($directChildCategoryId)) {
                                $directChildCategory->setParentCategoryId($parentCategory->getId());
                                $this->app->managers->categories->updateOne($directChildCategory);
                            } else {
                                $this->app->container->logger->debug('Child Category[' . $directChildCategoryId . '] not exists... fix category structure...');
                                //@todo delete category child from categories tree...
                            }
                        }
                    } elseif ($e->check(Item::getTable())) {
                        //there are items with such category - fix them first @todo...
                        $this->app->container->logger->debug(implode(' ', [
                            'We do have non-existing categories in the item table...',
                            'but it should be fixed already (ItemManager::updateMany)'
                        ]));
                    } elseif ($e->check(Catalog::getTable())) {
                        //there are catalog pages with such category - fix them first @todo...
                        $this->app->container->logger->debug(implode(' ', [
                            'We do have non-existing categories in the page_catalog table...',
                            'but it should be fixed already (PageCatalogManager::updateMany)'
                        ]));
                    } else {
                        $this->app->container->logger->debug('Can\'t delete category[' . $category->getId() . ']... skipping...');
                        break;
                    }
                }
            }
        }

        $this->app->container->memcache->flush();

        return true;
    }

    /**
     * Params:
     * 1) source_category_id - int - category id (required)
     * 2) target_category_id - int - category id (required)
     * 3) target_tag - int[]|string[]|mixed[] - tag name or id (required)
     * 4) rotate_off - 1|0 - rotate ftdbms & mcms (options, default = 1)
     *
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws Throwable
     */
    public function doTransferCategoryToCategoryWithTag()
    {
        if (!$sourceCategoryId = (int) trim($this->app->request->get('param_1'))) {
            throw (new BadRequestHttpException)->setInvalidParam('source_category_id');
        }

        if (!$sourceCategory = $this->app->managers->categories->find($sourceCategoryId)) {
            throw (new NotFoundHttpException)->setNonExisting('source_category');
        }

        if (!$targetCategoryId = (int) trim($this->app->request->get('param_2'))) {
            throw (new BadRequestHttpException)->setInvalidParam('target_category_id');
        }

        if (!$targetCategory = $this->app->managers->categories->find($targetCategoryId)) {
            throw (new NotFoundHttpException)->setNonExisting('target_category');
        }

        if (!$targetTag = trim($this->app->request->get('param_3'))) {
            throw (new BadRequestHttpException)->setInvalidParam('target_tag');
        }

        $targetTags = explode(',', $targetTag);

        foreach ($targetTags as $targetTag) {
            $tmp = Entity::normalizeUri($targetTag);

            if ($sourceCategory->getUri() == $tmp) {
                throw new Exception('Conflict between source category uri[' . $sourceCategory->getUri() . '] and tag uri[' . $tmp . ']');
            }
        }

        foreach ($targetTags as $i => $targetTag) {
            if (is_numeric($targetTag)) {
                if (!$targetTags[$i] = $this->app->managers->tags->find($targetTag)) {
                    throw (new NotFoundHttpException)->setNonExisting('target_tag');
                }
            } elseif (is_string($targetTag)) {
                if (!$targetTagByName = $this->app->managers->tags->clear()->setWhere(['name' => $targetTag])->getObject()) {
                    $targetTags[$i] = new Tag(['name' => $targetTag]);
                    $this->app->managers->tags->insertOne($targetTags[$i]);
                } else {
                    $targetTags[$i] = $targetTagByName;
                }
            }
        }

        /** @var Tag[] $targetTags */

        $this->app->container->mysql->makeTransaction(function (Mysql $mysql) use ($sourceCategory, $targetCategory, $targetTags) {
            foreach ($targetTags as $targetTag) {
                $query = new MysqlQuery(['params' => []]);
                $query->text = implode(' ', [
                    'INSERT IGNORE INTO',
                    $mysql->quote(TagItem::getTable()),
                    '(' . $mysql->quote('item_id') . ', ' . $mysql->quote('tag_id') . ')',
                    'SELECT ' . $mysql->quote(Item::getPk()) . ', ' . $targetTag->getId(),
                    $mysql->makeFromSQL(Item::getTable()),
                    $mysql->makeWhereSQL(['category_id' => $sourceCategory->getId()], $query->params, null, $query->placeholders)
                ]);

                $mysql->req($query);
            }

            $where = ['category_id' => $sourceCategory->getId()];

            if (1 == count($targetTags)) {
                $this->app->managers->catalog->updateMany(['category_id' => $targetCategory->getId(), 'tag_id' => $targetTags[0]->getId()], $where, ['ignore' => true]);
            } else {
                $columns = array_keys(Catalog::getColumns());

                foreach ($targetTags as $targetTag) {
                    $query = new MysqlQuery(['params' => []]);
                    $query->text = implode(' ', [
                        'INSERT IGNORE INTO' . ' ' . $mysql->quote(Catalog::getTable()),
                        '(' . implode(', ', array_map(function ($column) use ($mysql) {
                            return $mysql->quote($column);
                        }, $columns)) . ')',
                        'SELECT',
                        implode(', ', array_map(function ($column) use ($targetCategory, $targetTag, $mysql, $query) {
                            if ('category_id' == $column) {
                                $query->params[] = $targetCategory->getId();
                                return '?';
                            } elseif ('tag_id' == $column) {
                                $query->params[] = $targetTag->getId();
                                return '?';
                            } else {
                                return $mysql->quote($column);
                            }
                        }, $columns)),
                        'FROM ' . $mysql->quote(Catalog::getTable()),
                        $mysql->makeWhereSQL($where, $query->params, null, $query->placeholders)
                    ]);

                    $mysql->req($query);
                }

                $this->app->managers->catalog->deleteMany([
                    'category_id' => $sourceCategory->getId()
                ]);
            }

            $this->app->managers->items->updateMany(['category_id' => $targetCategory->getId()], [
                'category_id' => $sourceCategory->getId()
            ], ['ignore' => true]);

            (new CatalogUriManager($this->app))
                ->addRedirect($sourceCategory->getCatalogUri(), new URI([
                    'category_id' => $targetCategory->getId(),
                    'tag_id' => array_map(function ($targetTag) {
                        /** @var Tag $targetTag */
                        return $targetTag->getId();
                    }, $targetTags)
                ]));

            $this->app->managers->categories->deleteOne($sourceCategory);
        });

        if (1 == $this->app->request->get('param_4', 1)) {
//            $this->app->storage->elastic->restart($this->app);
            //@todo...
//            $this->app->storage->elastic->rotate($this->app);
            $this->app->container->memcache->flush();
        }

        return true;
    }

    /**
     * Params:
     * 1) source_category_id - int - category id (required) !should exists
     * 2) target_category_id - int - category id (required) !should exists
     * 3) rotate_off - 1|0 - rotate ftdbms & mcms (options, default = 1)
     *
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws Throwable
     */
    public function doTransferCategoryToCategory()
    {
        if (!$sourceCategoryId = (int) trim($this->app->request->get('param_1'))) {
            throw (new BadRequestHttpException)->setInvalidParam('source_category_id');
        }

        if (!$sourceCategory = $this->app->managers->categories->find($sourceCategoryId)) {
            throw (new NotFoundHttpException)->setNonExisting('source_category');
        }

        if (!$targetCategoryId = (int) trim($this->app->request->get('param_2'))) {
            throw (new BadRequestHttpException)->setInvalidParam('target_category_id');
        }

        if (!$targetCategory = $this->app->managers->categories->find($targetCategoryId)) {
            throw (new NotFoundHttpException)->setNonExisting('target_category');
        }

        $rotate = 1 == $this->app->request->get('param_4', 0);

        $this->app->container->mysql->makeTransaction(function () use ($sourceCategory, $targetCategory) {
            $where = ['category_id' => $sourceCategory->getId()];

            $this->app->managers->items->updateMany(['category_id' => $targetCategory->getId()], $where, ['ignore' => true]);

            (new CatalogUriManager($this->app))
                ->addRedirect($sourceCategory->getCatalogUri(), new URI([
                    'category_id' => $targetCategory->getId()
                ]));

            $this->app->managers->catalog->deleteMany($where);

            $this->app->managers->categories->deleteOne($sourceCategory);
        });

        if ($rotate) {
            //@todo...
//            $this->app->storage->elastic->rotate($this->app);
            $this->app->container->memcache->flush();
        }

        return true;
    }

    public function doSyncIsLeafCategories()
    {
        $aff = 0;
        $output = $this->app->managers->categories->syncIsLeafColumn(function (CategoryEntity $category) use (&$aff) {
            $aff++;
            $this->output($category->getName() . '[' . $category->getId() . ']: ' . ($category->isLeaf() ? '1' : '0'));
        });
        $this->output($output ? ('DONE[aff=' . $aff . ']') : 'FAILED');

        return true;
    }
}