<?php

namespace SNOWGIRL_SHOP\Vendor;

use SNOWGIRL_SHOP\Vendor;
use SNOWGIRL_SHOP\Entity\Item;

class LamodaRu extends Vendor
{
    public function getBuySelector()
    {
//        return '.ii-product__wrapper .product__cart-add-button';
        return '.ii-product__buy[data-in-stock="true"]';
    }

    public function getItemTargetLink(Item $item)
    {
        return 'https://www.lamoda.ru/p/' . strtolower($item->getPartnerItemId()) . '/';
    }
}