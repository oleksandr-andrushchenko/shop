<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 10/3/18
 * Time: 7:19 PM
 */

namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_CORE\Entity;

/**
 * Interface GoLinkHolderInterface
 * @package SNOWGIRL_SHOP\Manager
 */
interface GoLinkBuilderInterface
{
    public function getGoLink(Entity $entity, $source = null);
}