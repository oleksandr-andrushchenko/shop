<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 11/2/16
 * Time: 1:20 AM
 */
namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_SHOP\Manager\Item\Attr;
use SNOWGIRL_SHOP\Entity\Season as SeasonEntity;

/**
 * Class Season
 * @property \SNOWGIRL_SHOP\Entity\Season $entity
 * @method static \SNOWGIRL_SHOP\Entity\Season getItem($id)
 * @method Season clear()
 * @method Season setLimit($limit)
 * @method SeasonEntity find($id)
 * @method SeasonEntity[] findMany(array $id)
 * @package SNOWGIRL_SHOP\Manager
 */
class Season extends Attr
{
}