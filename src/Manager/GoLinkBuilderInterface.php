<?php

namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_CORE\Entity;

interface GoLinkBuilderInterface
{
    public function getGoLink(Entity $entity, $source = null);
}