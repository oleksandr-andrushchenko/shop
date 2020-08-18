<?php

namespace SNOWGIRL_SHOP\Vendor;

use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Vendor;

class AsosComRu extends Vendor
{
    public function getBuySelector(): ?string
    {
        return '.asos-product .add-item .add-button';
    }

    public function getItemTargetLink(Item $item): ?string
    {
        if (!$partnerLink = $item->getPartnerLink()) {
            return null;
        }

        if (!$query = parse_url($partnerLink, PHP_URL_QUERY)) {
            return null;
        }

        parse_str($query, $output);

        if (empty($output['ulp'])) {
            return null;
        }

        return $output['ulp'];
    }
}