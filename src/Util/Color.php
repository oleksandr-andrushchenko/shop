<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Util;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Entity\Color as ColorEntity;
use SNOWGIRL_SHOP\Http\HttpApp;

/**
 * @property HttpApp|ConsoleApp app
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