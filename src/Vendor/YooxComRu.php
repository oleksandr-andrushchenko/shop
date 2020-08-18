<?php

namespace SNOWGIRL_SHOP\Vendor;

use SNOWGIRL_SHOP\Vendor;

class YooxComRu extends Vendor
{
    public function getBuySelector(): ?string
    {
        return '.js-add-to-cart-container';
    }
}