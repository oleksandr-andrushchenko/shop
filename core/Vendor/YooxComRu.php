<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 12/20/17
 * Time: 12:41 PM
 */
namespace SNOWGIRL_SHOP\Vendor;

use SNOWGIRL_SHOP\Vendor;

/**
 * Class YooxComRu
 * @package SNOWGIRL_SHOP\Vendor
 */
class YooxComRu extends Vendor
{
    public function getBuySelector()
    {
        return '.js-add-to-cart-container';
    }
}