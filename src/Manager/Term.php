<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 4/9/17
 * Time: 10:02 AM
 */
namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\Entity\Term as TermEntity;
use SNOWGIRL_CORE\Manager;

/**
 * Class Term
 * @package SNOWGIRL_SHOP\Manager
 */
abstract class Term extends Manager
{
    public function getValueToComponentId(array $where = null, $isLowercase = false)
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