<?php

namespace SNOWGIRL_SHOP\Manager\Page\Catalog\DataProvider;

class IndexerDataProvider extends \SNOWGIRL_CORE\Manager\DataProvider\IndexerDataProvider
{
    protected function getListByQuerySort(): array
    {
        $output = parent::getListByQuerySort();

        array_unshift($output, 'count:desc');
        return $output;
    }
}