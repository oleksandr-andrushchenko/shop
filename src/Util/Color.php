<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 3/30/18
 * Time: 6:03 PM
 */
namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Util;
use SNOWGIRL_SHOP\App;
use SNOWGIRL_SHOP\Entity\Color as ColorEntity;

/**
 * Class Color
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