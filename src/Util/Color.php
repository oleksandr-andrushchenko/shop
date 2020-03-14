<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Util;
use SNOWGIRL_CORE\AbstractApp;
use SNOWGIRL_SHOP\Entity\Color as ColorEntity;

/**
 * Class Color
 *
 * @property App app
 * @package SNOWGIRL_SHOP\Util
 */
class Color extends Util
{
    public function doFixColorsUris()
    {
        foreach ($this->app->managers->colors->clear()->getObjects() as $object) {
            /** @var ColorEntity $object */
            $object->setUri($object->getName());
            $this->app->managers->colors->updateOne($object);
        }

        return true;
    }
}