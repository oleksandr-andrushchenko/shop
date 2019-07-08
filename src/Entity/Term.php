<?php

namespace SNOWGIRL_SHOP\Entity;

use SNOWGIRL_CORE\Entity;

abstract class Term extends Entity
{
    abstract public function getComponentId();

    abstract public function getLang();

    abstract public function getValue();
}