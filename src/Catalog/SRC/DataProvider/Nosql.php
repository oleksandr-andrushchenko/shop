<?php

namespace SNOWGIRL_SHOP\Catalog\SRC\DataProvider;

use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Catalog\SRC\DataProvider;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Item;

use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalogManager;

class Nosql extends DataProvider
{
    public function getItemsAttrs()
    {
        return $this->src->getURI()->getApp()->managers->items
            ->setStorage(Manager::STORAGE_NOSQL)
            //@todo left $this->entities only
//            ->setColumns(array_merge([Item::getPk()], $this->uri->getApp()->managers->catalog->getComponentsPKs()))
            ->setColumns(array_merge([Item::getPk()], array_keys($this->src->getEntities())))
            ->setWhere($this->getWhere())
            ->setOrders($this->getOrder(true))
            ->setOffset($this->src->getOffset())
            ->setLimit($this->src->getLimit())
            ->getArrays();
    }

    public function getWhere($raw = false)
    {
        $output = [];

        $params = $this->src->getURI()->getParamsByTypes('filter');

        if (isset($params[URI::SPORT])) {
            $output['is_sport'] = 1;
        } else {
            if (!isset($params['tag_id'])) {
                $output['is_sport'] = 0;
            }
        }

        if (isset($params[URI::SIZE_PLUS])) {
            $output['is_size_plus'] = 1;
        } else {
            if (!isset($params['tag_id'])) {
                $output['is_size_plus'] = 0;
            }
        }

        foreach (Manager::mapEntitiesAddPksAsKeys(PageCatalogManager::getComponentsOrderByRdbmsKey()) as $pk => $entity) {
            if (isset($params[$pk])) {
                $v = $params[$pk];

                if (is_array($v)) {
                    array_walk($v, function (&$vv) {
                        $vv = (int)$vv;
                    });
                    $output[$pk] = ['$in' => $v];
                } else {
                    $output[$pk] = (int)$v;
                }
            }
        }

        if (isset($output['category_id'])) {
            $categoryId = (int)$params['category_id'];
            $output['category_id'] = ['$in' => $this->src->getURI()->getApp()->managers->categories->getChildrenIdFor($categoryId)];
        }

        if (isset($params[URI::PRICE_FROM])) {
            $output['price'] = ['$gt' => (int)$params[URI::PRICE_FROM]];
        }

        if (isset($params[URI::PRICE_TO])) {
            if (!isset($output['price'])) {
                $output['price'] = [];
            }

            $output['price']['$lte'] = (int)$params[URI::PRICE_TO];
        }

        if (isset($params[URI::SALES])) {
            $output['old_price'] = ['$gt' => 0];
        }

//        if (!isset($params[URI::PRICE_FROM]) && !isset($params[URI::PRICE_TO])) {
        //@todo default range (economy)
//        }

        return $output;
    }

    public function getOrder($cache = false)
    {
        $output = [];

        $info = $this->src->getOrderInfo();

        if ($cache) {
            $output[$info->cache_column] = SORT_ASC;
            return $output;
        }

        //@todo...

        return $output;
    }

    public function getTotalCount()
    {
        return $this->src->getURI()->getApp()->managers->items->clear()
            ->setStorage(Manager::STORAGE_NOSQL)
            ->setWhere($this->getWhere())
            ->getCount();
    }
}