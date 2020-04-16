<?php

namespace SNOWGIRL_SHOP\Manager\Page\Catalog;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\AbstractApp as App;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Entity\Page\Catalog;
use SNOWGIRL_SHOP\Http\HttpApp;

class IndexerHelper
{
    private $itemPk;
    private $columns;
    private $searchColumns;
    private $columnsOptions;

    private $prepared;

    public function getDocumentByArray(array $entity): array
    {
        $document = array_filter($entity, function ($v) {
            return null !== $v;
        });

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
                        if ($document[$column]) {
                        } else {
                            $document[$column] = $options['default'];
                        }
                        break;
                    default:
                        break;
                }
            }
        }

        foreach ($this->searchColumns as $column) {
            if (isset($document[$column])) {
                $document[$column . '_length'] = mb_strlen($document[$column]);
            }
        }

        if (isset($document['meta'])) {
            if ($meta = json_decode($document['meta'], true)) {
                $document['count'] = $meta['count'];
            }

            unset($document['meta']);
        }

        unset($document[$this->itemPk]);

        return $document;
    }

    public function getDocumentByEntity(Catalog $entity): array
    {
        return $this->getDocumentByArray($entity->getAttrs());
    }

    /**
     * @param App|HttpApp|ConsoleApp $app
     */
    public function prepareData(App $app)
    {
        if ($this->prepared) {
            return;
        }

        $this->itemPk = $app->managers->catalog->getEntity()->getPk();
        $this->searchColumns = $app->managers->catalog->findColumns(Entity::SEARCH_IN);
        $this->columns = array_merge($this->searchColumns, [
            $app->managers->catalog->getEntity()->getPk(),
            'uri',
            'meta'
        ]);
        $this->columnsOptions = Arrays::filterByKeysArray(
            $app->managers->catalog->getEntity()->getColumns(),
            $this->columns
        );

        $this->prepared = true;
    }

    public function getSearchColumns(): array
    {
        return $this->searchColumns;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }
}