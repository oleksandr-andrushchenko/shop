<?php

namespace SNOWGIRL_SHOP\Vendor;

use SNOWGIRL_SHOP\Vendor;
use SNOWGIRL_SHOP\Entity\Item;

class WildberriesRu extends Vendor
{
    public function getBuySelector()
    {
        return '.order .cart-button.buy:not(.hide)';
    }

    public function getItemTargetLink(Item $item)
    {
        return 'https://www.wildberries.ru/catalog/' . strtolower($item->getPartnerItemId()) . '/detail.aspx';
    }
}