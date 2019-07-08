<?php

namespace SNOWGIRL_SHOP\Manager\Item\Attr\DataProvider;

use MongoDB\BSON\Regex;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Service\Nosql\Mongo;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Item\Attr\DataProvider;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalogManager;

class Nosql extends DataProvider
{
    use \SNOWGIRL_CORE\Manager\DataProvider\Traits\Nosql;

    public function getFiltersCountsByUri(URI $uri, $query = null, $prefix = false)
    {
        $pipeline = [];

        /** @var Mongo $db */
        $db = $this->manager->getApp()->services->nosql(null, null, $this->manager->getMasterServices());

        $pk = $this->manager->getEntity()->getPk();
        $table = $this->manager->getEntity()->getTable();
        $itemTable = $this->manager->getApp()->managers->items->getEntity()->getTable();

        $where = $uri->getSRC()->getDataProvider('nosql')->getWhere();

        if ($this->manager->getQuery()->where) {
            $where = array_merge($where, Arrays::cast($this->manager->getQuery()->where));
        }

        $sva = $this->manager->mapEntitiesAddPksAsKeys(PageCatalogManager::getSvaComponents());
        $mva = $this->manager->mapEntitiesAddPksAsKeys(PageCatalogManager::getMvaComponents());

        if (isset($sva[$pk])) {
            unset($where[$pk]);
        }

        $pipeline[] = ['$match' => $where];

        if (isset($mva[$pk])) {
            $pipeline[] = ['$unwind' => '$' . $pk];
        }

        if ($query) {
            $pipeline[] = ['$lookup' => [
                'from' => $table,
                'localField' => $pk,
                'foreignField' => '_id',
                'as' => $table
            ]];

            $searchIn = $this->manager->findColumns(Entity::SEARCH_IN)[0];

            $pipeline[] = ['$match' => [
                $table . '.0.' . $searchIn => new Regex(($prefix ? '^' : '') . $query, 'i')
            ]];
        }

        $pipeline[] = ['$group' => [
            '_id' => '$' . $pk,
            'cnt' => ['$sum' => 1]
        ]];

        if ($this->manager->getQuery()->limit) {
            $pipeline[] = ['$sort' => ['cnt' => -1]];
            $pipeline[] = ['$limit' => (int)$this->manager->getQuery()->limit];
        }

        return array_map(function ($item) use ($pk) {
            $item[$pk] = $item['_id'];
            unset($item['_id']);
            return $item;
        }, $db->aggregate($itemTable, $pipeline));
    }
}