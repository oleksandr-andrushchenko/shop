<?php

namespace SNOWGIRL_SHOP\Manager\Page\Catalog\DataProvider;

class Indexer extends \SNOWGIRL_CORE\Manager\DataProvider\Indexer
{
    protected function getListByQuerySort(): array
    {
        $output = parent::getListByQuerySort();

        array_unshift($output, 'count:desc');
        return $output;
    }
}