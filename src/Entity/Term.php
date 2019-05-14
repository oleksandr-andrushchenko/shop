<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 4/9/17
 * Time: 9:58 AM
 */
namespace SNOWGIRL_SHOP\Entity;

use SNOWGIRL_CORE\Entity;

/**
 * Class Term
 * @package SNOWGIRL_SHOP\Entity
 */
abstract class Term extends Entity
{
    abstract public function getComponentId();

    abstract public function getLang();

    abstract public function getValue();
}