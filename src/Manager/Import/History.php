<?php

namespace SNOWGIRL_SHOP\Manager\Import;

use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
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
    /**
     * @param ImportSource $importSource
     *
     * @return bool|null
     */
    public function isOkLastImport(ImportSource $importSource)
    {
        /** @var ImportHistoryEntity $tmp */
        $tmp = $this->copy(true)
            ->setWhere([
                'import_source_id' => $importSource->getId(),
            ])
            ->setOrders([$this->getEntity()->getPk() => SORT_DESC])
            ->setLimit(1)
            ->getObject();

        if ($tmp) {
            if ($tmp->getError()) {
                return false;
            }

            return (time() - strtotime($tmp->getCreatedAt())) / (24 * 60 * 60) < 1;
        }

        return null;
    }

    public function getLast(ImportSource $importSource): ?ImportHistoryEntity
    {
        return $this->copy(true)
            ->setWhere([
                'import_source_id' => $importSource->getId(),
            ])
            ->setOrders([$this->getEntity()->getPk() => SORT_DESC])
            ->setLimit(1)
            ->getObject();
    }

    /**
     * @todo fix
     *
     * @param ImportSource[] $importSources
     *
     * @return array
     */
    public function isOkLastImports(array $importSources)
    {
        $output = [];

        $tmp = $this->copy(true)
            ->setColumns(['import_source_id', 'created_at'])
            ->setWhere([
                'import_source_id' => array_map(function (ImportSource $importSource) {
                    return $importSource->getId();
                }, $importSources),
                'is_ok' => 1
            ])
            ->setOrders([$this->getEntity()->getPk() => SORT_DESC])
            ->setGroups(['import_source_id'])
            ->getArrays('import_source_id');

        foreach ($importSources as $importSource) {
            $id = $importSource->getId();
            $output[$id] = isset($tmp[$id]) ? (time() - strtotime($tmp[$id]['created_at'])) / (24 * 60 * 60) < 7 : null;
        }

//        return $output;
    }
}