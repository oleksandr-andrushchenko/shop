<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/1/19
 * Time: 7:09 PM
 */

namespace SNOWGIRL_SHOP\Manager\Page\Catalog\DataProvider;

class Elastic extends \SNOWGIRL_CORE\Manager\DataProvider\Elastic
{
    protected function getListByQuerySort(): array
    {
        $output = parent::getListByQuerySort();

        array_unshift($output, 'count:desc');
        return $output;
    }
}