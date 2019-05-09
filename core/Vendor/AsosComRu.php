<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 12/20/17
 * Time: 12:55 AM
 */
namespace SNOWGIRL_SHOP\Vendor;

use SNOWGIRL_SHOP\Vendor;

/**
 * Class AsosComRu
 * @package SNOWGIRL_SHOP\Vendor
 */
class AsosComRu extends Vendor
{
    public function getBuySelector()
    {
        return '.asos-product.pg-in-stock';
    }
}