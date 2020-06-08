<?php

namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Entity\Term as TermEntity;
use SNOWGIRL_CORE\Helper\Arrays;

abstract class Term extends Manager
{
    public function getValueToComponentId(array $where = null, bool $isLowercase = false): array
    {
        $output = [];

        foreach ($this->clear()->setWhere($where)->getObjects() as $term) {
            /** @var TermEntity $term */
            $output[$term->getValue()] = $term->getComponentId();
        }

        if ($isLowercase) {
            $output = Arrays::mapByKeyMaker($output, function ($key) {
                return mb_strtolower($key);
            });
        }

        return $output;
    }
}