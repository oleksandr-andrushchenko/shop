<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 12/20/17
 * Time: 12:26 AM
 */
namespace SNOWGIRL_SHOP\Vendor;

use SNOWGIRL_SHOP\Vendor;
use SNOWGIRL_SHOP\Entity\Item;

/**
 * Class WildberriesRu
 * @package SNOWGIRL_SHOP\Vendor
 */
class WildberriesRu extends Vendor
{
    public function getBuySelector()
    {
        return '.order .cart-button.buy:not(.hide)';
    }

    public function getItemTargetLink(Item $item)
    {
        return 'https://www.wildberries.ru/catalog/' . strtolower($item->getUpc()) . '/detail.aspx';
    }
}