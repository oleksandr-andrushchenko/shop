<?php

namespace SNOWGIRL_SHOP\Vendor;

use SNOWGIRL_SHOP\Entity\Item;

class LamodaUa extends LamodaRu
{
    public function getItemTargetLink(Item $item)
    {
        return 'https://www.lamoda.ua/p/' . strtolower($item->getPartnerItemId()) . '/';
    }
}