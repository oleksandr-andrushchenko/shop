<?php

namespace SNOWGIRL_SHOP\Manager\Item;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\AbstractApp as App;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Http\HttpApp;

class IndexerHelper
{
    private $itemPk;
    private $columns;
    private $searchColumns;
    private $sva;
    private $mva;
    private $columnsOptions;

    /**
     * @var Manager[]
     */
    private $managers;
    private $injectAttrIdToName;
    private $prepared;

    public function getDocumentByArray(array $entity): array
    {
        $document = array_filter($entity, function ($v) {
            return null !== $v;
        });;

        foreach ($this->columnsOptions as $column => $options) {
            if (isset($document[$column])) {
                switch ($options['type']) {
                    case Entity::COLUMN_FLOAT:
                        if ($document[$column]) {
                            $document[$column] = (float) $document[$column];
                        } else {
                            $document[$column] = $options['default'];
                        }
                        break;
                    case Entity::COLUMN_INT:
                        if ($document[$column]) {
                            $document[$column] = (int) $document[$column];
                        } else {
                            $document[$column] = $options['default'];
                        }
                        break;
                    case Entity::COLUMN_TIME:
                        if (!$document[$column]) {
                            $document[$column] = $options['default'];
                        }
                        break;
                    default:
                        break;
                }
            }
        }

        if (isset($document['old_price'])) {
            $document['is_sales'] = $document['old_price'] > 0 ? 1 : 0;
            unset($document['old_price']);
        } else {
            $document['is_sales'] = 0;
        }

        foreach ([
                     'is_sport',
                     'is_size_plus',
                     'is_sales',
                     'is_in_stock',
                 ] as $column) {
            if (isset($document[$column])) {
                $document[$column] = 1 == $document[$column];
            }
        }

        foreach ($this->sva as $pk => $table) {
            if (isset($document[$pk])) {
                if ($document[$pk]) {
                    $document[$pk] = (int) $document[$pk];

//                                if ('category_id' == $pk) {
//                                    $document[$pk] = $categoryManager->getChildrenIdFor($document[$pk]);
//                                    $document['category_ids'] = $categoryManager->getChildrenIdFor($document[$pk]);
//                                }
                } else {
                    unset($document[$pk]);
//                            $document[$pk] = null;
                }
            }
        }

        foreach ($this->mva as $pk => $table) {
            if (isset($document[$pk])) {
                if ($document[$pk]) {
                    $document[$pk] = array_map('intval', explode(',', $document[$pk]));
                } else {
                    unset($document[$pk]);
                }
            }
        }

        foreach (array_keys($this->injectAttrIdToName) as $attrIdToInject) {
            if (isset($document[$attrIdToInject])) {
                $manager = $this->managers[$attrIdToInject];
                $table = $manager->getEntity()->getTable();

                if (is_array($document[$attrIdToInject])) {
                    $document[$table] = [];

                    foreach ($document[$attrIdToInject] as $id) {
                        if (isset($this->injectAttrIdToName[$attrIdToInject][$id])) {
                            $document[$table][] = [
                                'id' => $id,
                                'name' => $this->injectAttrIdToName[$attrIdToInject][$id]
                            ];
                        }
                    }
                } else {
                    if (isset($this->injectAttrIdToName[$attrIdToInject][$document[$attrIdToInject]])) {
                        $document[$table] = [
                            'id' => $document[$attrIdToInject],
                            'name' => $this->injectAttrIdToName[$attrIdToInject][$document[$attrIdToInject]]
                        ];
                    }
                }

                unset($document[$attrIdToInject]);
            }
        }

        foreach ($this->searchColumns as $column) {
            if (isset($document[$column])) {
                $document[$column . '_length'] = mb_strlen($document[$column]);
            }
        }

        unset($document[$this->itemPk]);

        return $document;
    }

    public function getDocumentByEntity(Item $entity): array
    {
        return $this->getDocumentByArray($entity->getAttrs());
    }

    /**
     * @param App|HttpApp|ConsoleApp $app $app
     * @return array
     */
    public static function getAjaxSuggestionsAttrPkToTable(App $app): array
    {
        return [
            ($entity = $app->managers->brands->getEntity())->getPk() => $entity->getTable(),
            ($entity = $app->managers->colors->getEntity())->getPk() => $entity->getTable(),
            ($entity = $app->managers->materials->getEntity())->getPk() => $entity->getTable(),
        ];
    }

    /**
     * @param App|HttpApp|ConsoleApp $app
     */
    public function prepareData(App $app)
    {
        if ($this->prepared) {
            return;
        }

        $inStockOnly = !!$app->configMasterOrOwn('catalog.in_stock_only', false);

        $ajaxSuggestionsAttrPkToTable = self::getAjaxSuggestionsAttrPkToTable($app);
        $this->itemPk = $app->managers->items->getEntity()->getPk();

        $this->columns = [
            $this->itemPk,
            'is_sport',
            'is_size_plus',
            'old_price',
            'price',

//            'order_desc_relevance',
//            'order_desc_rating',
//            'order_asc_price',
//            'order_desc_price'
        ];

        if (!$inStockOnly) {
            $this->columns[] = 'is_in_stock';
        }

        $this->searchColumns = $app->managers->items->findColumns(Entity::SEARCH_IN);

        foreach ($this->searchColumns as $column) {
            $this->columns[] = $column;
        }

        $this->sva = $app->managers->catalog->getSvaPkToTable();

        foreach (array_keys($this->sva) as $pk) {
            $this->columns[] = $pk;
        }

        $this->mva = $app->managers->catalog->getMvaPkToTable();

        foreach (array_keys($this->mva) as $pk) {
            $this->columns[] = $pk;
        }

        $this->columnsOptions = Arrays::filterByKeysArray($app->managers->items->getEntity()->getColumns(), $this->columns);

        /** @var Manager[] $managers */
        $this->managers = [];

        //which attrs name to inject into index
        $this->injectAttrIdToName = [];

        foreach ($ajaxSuggestionsAttrPkToTable as $attrPk => $attrTable) {
            /** @var Manager $attrManager */
            $attrManager = $app->managers->getByTable($attrTable);
            $entity = $attrManager->getEntity();
            $this->injectAttrIdToName[$attrPk] = $app->utils->attrs->getIdToName($entity->getClass(), true);
            $this->managers[$attrPk] = $attrManager;
        }

        $this->prepared = true;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getMva(): array
    {
        return $this->mva;
    }
}