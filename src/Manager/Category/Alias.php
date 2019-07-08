<?php

namespace SNOWGIRL_SHOP\Manager\Category;

use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_SHOP\Entity\Category\Alias as CategoryAliasEntity;
use SNOWGIRL_CORE\Entity\Redirect;

/**
 * @todo    ...
 * Class Alias
 * @method CategoryAliasEntity find($id)
 * @package SNOWGIRL_SHOP\Manager\Category
 */
class Alias extends Manager
{
    protected function onInsert(Entity $entity)
    {
        /** @var CategoryAliasEntity $entity */

        $output = parent::onInsert($entity);

        if (!$entity->getUri()) {
            $entity->setUri(CategoryAliasEntity::normalizeUri($entity->getName()));
        }

        if ($entity->hasAttr('name_hash') && !$entity->issetAttr('name_hash')) {
            $entity->set('name_hash', CategoryAliasEntity::normalizeHash($entity->getName()));
        }

        return $output;
    }

    protected function onUpdate(Entity $entity)
    {
        /** @var CategoryAliasEntity $entity */

        $output = parent::onUpdate($entity);

        if ($entity->isAttrChanged('name')) {
            if ($entity->isAttrChanged('uri') && ($entity->getPrevAttr('uri') == CategoryAliasEntity::normalizeUri($entity->getPrevAttr('name')))) {
                $entity->setUri($entity->getName());
            } elseif ($entity->getUri() == CategoryAliasEntity::normalizeUri($entity->getPrevAttr('name'))) {
                $entity->setUri($entity->getName());
            }
        }

        if ($entity->isAttrChanged('uri')) {
            if (!$entity->getUri()) {
                $entity->setUri(CategoryAliasEntity::normalizeUri($entity->getName()));
            }
        }

        if ($entity->hasAttr('name_hash') && $entity->isAttrChanged('name')) {
            $entity->set('name_hash', CategoryAliasEntity::normalizeHash($entity->getName()));
        }

        return $output;
    }

    protected function onUpdated(Entity $entity)
    {
        /** @var CategoryAliasEntity $entity */

        $output = parent::onUpdated($entity);

        if ($entity->isAttrChanged('uri')) {
            $output = $output && $this->app->managers->redirects->save((new Redirect)
                    ->setUriFrom($entity->getPrevAttr('uri'))
                    ->setUriTo($entity->getUri()));
        }

        return $output;
    }

    protected function _onDelete(Entity $entity)
    {
        /** @var CategoryAliasEntity $entity */

        $output = parent::onDelete($entity);

        if ($this->isSva()) {
            if ($this->app->managers->items->clear()
                ->setWhere([$this->entity->getPk() => $entity->getId()])
                ->setLimit(1)
                ->getArray()
            ) {
                throw new Exception('there are items with this ' . $this->entity->getTable());
            }
        }

        if ($this->app->managers->catalog->clear()
            ->setWhere([$this->entity->getPk() => $entity->getId()])
            ->setLimit(1)
            ->getArray()
        ) {
            throw new Exception('there are catalog pages with this ' . $this->entity->getTable());
        }

        if ($this->isMva()) {
            $this->getMvaLinkManager()->deleteMany([
                $this->entity->getPk() => $entity->getId()
            ]);
        }

        return $output;
    }
}