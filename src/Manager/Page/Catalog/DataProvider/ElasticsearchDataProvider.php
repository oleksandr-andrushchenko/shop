<?php

namespace SNOWGIRL_SHOP\Manager\Page\Catalog\DataProvider;

class ElasticsearchDataProvider extends \SNOWGIRL_CORE\Manager\DataProvider\ElasticsearchDataProvider
{
    protected function getListByQuerySort(): array
    {
        $output = parent::getListByQuerySort();

        array_unshift($output, 'count:desc');
        return $output;
    }
}