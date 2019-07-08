<?php

namespace SNOWGIRL_SHOP\Manager\Import;

use SNOWGIRL_SHOP\Entity\Import\Source as ImportSourceEntity;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Entity\Import\History as ImportHistoryEntity;

/**
 * Class History
 *
 * @package SNOWGIRL_SHOP\Manager\Import
 * @method static History factory($app)
 */
class History extends Manager
{
    public function isOkLastImport(ImportSourceEntity $importSource)
    {
        /** @var ImportHistoryEntity $tmp */
        $tmp = $this->copy(true)
            ->setWhere([
                $importSource->getPk() => $importSource->getId(),
                'is_ok' => 1
            ])
            ->setOrders(['created_at' => SORT_DESC])
            ->getObject();

        if ($tmp) {
            return (time() - strtotime($tmp->getCreatedAt())) / (24 * 60 * 60) < 7;
        }

        return null;
    }
}