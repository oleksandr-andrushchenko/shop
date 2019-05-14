<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 3/19/18
 * Time: 10:10 PM
 */
namespace SNOWGIRL_SHOP\Import\Admitad;

use SNOWGIRL_SHOP\Import\Admitad;

/**
 * Class GroupPrice
 * @package SNOWGIRL_SHOP\Import\Admitad
 */
class GroupPrice extends Admitad
{
    protected function postNormalizeRow($row)
    {
        if (isset($row['description'])) {
            $row['description'] = htmlspecialchars_decode($row['description']);
        }
        
        return $row;
    }
}