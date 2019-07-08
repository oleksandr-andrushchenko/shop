<?php

namespace SNOWGIRL_SHOP\Vendor;

use SNOWGIRL_SHOP\Vendor;

class AsosComRu extends Vendor
{
    public function getBuySelector()
    {
        return '.asos-product.pg-in-stock';
    }
}