<?php

namespace SNOWGIRL_SHOP\Manager\Item;

use SNOWGIRL_CORE\Manager;
//use SNOWGIRL_SHOP\Manager\Item\Attr;
use SNOWGIRL_SHOP\Entity\Item;

class Image extends Manager
{
    public function getImages(Item $item)
    {
        $output = [];

        $output[] = $item->getImage();

        foreach ($this->setColumns(['image_id'])->setWhere(['item_id' => $item->getId()])->getItems() as $item) {
            $output[] = $item['image_id'];
        }

        return $output;
    }
}