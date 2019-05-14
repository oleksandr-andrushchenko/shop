<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 12/18/17
 * Time: 2:32 PM
 */

namespace SNOWGIRL_SHOP\Vendor;

use SNOWGIRL_SHOP\Vendor;
use SNOWGIRL_SHOP\Entity\Item;

/**
 * Class LamodaRu
 * @package SNOWGIRL_SHOP\Vendor
 */
class LamodaRu extends Vendor
{
    public function getBuySelector()
    {
        return '.ii-product__wrapper .product__cart-add-button';
    }

    public function getItemTargetLink(Item $item)
    {
        return 'https://www.lamoda.ru/p/' . strtolower($item->getUpc()) . '/';
    }
}