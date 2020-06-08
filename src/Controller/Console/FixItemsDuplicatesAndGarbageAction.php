<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\OutputTrait;
use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Helper\WalkChunk;
use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class FixItemsDuplicatesAndGarbageAction
{
    use PrepareServicesTrait;
    use OutputTrait;

    /**
     * @todo sync with Import::walkImport()
     *
     * @param App $app
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $aff = 0;

        $aff += $this->processDuplicates($app);
        $aff += $this->processGarbage($app);

        if ($aff) {
            (new DeleteItemsNonExistingMvaAction)($app);
        }

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            is_int($aff) ? "DONE: {$aff}" : 'FAILED',
        ]));
    }

    private function processDuplicates(App $app): int
    {
        $aff = 0;

        $attrUtil = $app->utils->attrs;

        foreach ($app->managers->sources->getObjects() as $source) {
            (new WalkChunk(1000))
                ->setFnGet(function ($page, $size) use ($app, $source) {
                    $pkQuoted = $app->container->db->quote($app->managers->items->getEntity()->getPk());

                    return $app->managers->items
                        ->setColumns([
                            new Expression('GROUP_CONCAT(' . $pkQuoted . ') AS ' . $pkQuoted),
                        ])
                        ->setWhere([
                            'import_source_id' => $source->getId(),
                        ])
                        ->setGroups([
                            'category_id',
                            'partner_link_hash',
                        ])
                        ->setHavings([
                            new Expression('COUNT(*) > 1'),
                        ])
                        ->setOffset(($page - 1) * $size)
                        ->setLimit($size)
                        ->getArrays();
                })
                ->setFnDo(function ($rows) use ($app, $attrUtil, &$aff) {
                    /** @var App $app */
                    $pk = $app->managers->items->getEntity()->getPk();

                    foreach ($rows as $row) {
                        $pkValues = explode(',', $row[$pk]);
                        $pkToKeep = (int)min($pkValues);
                        $pkValues = array_diff($pkValues, [$pkToKeep]);

                        $insert = [];
                        $delete = [$pk => []];
                        $mva = [];

                        foreach ($pkValues as $pkValue) {
                            $pkValue = (int)$pkValue;

                            $insert[] = [
                                'id_from' => $pkValue,
                                'id_to' => $pkToKeep
                            ];

                            $delete[$pk][] = $pkValue;
                            $mva[$pkValue] = $pkToKeep;
                        }

                        $aff += $app->managers->itemRedirects->insertMany($insert);
                        $aff += $app->managers->items->deleteMany($delete);
                        $aff += $attrUtil->doTransferMvaValues($mva);
                    }
                })
                ->run();
        }

        return $aff;
    }

    private function processGarbage(App $app): int
    {
        $aff = 0;

        $attrUtil = $app->utils->attrs;

        foreach ($app->managers->sources->getObjects() as $source) {
            (new WalkChunk(1000))
                ->setFnGet(function ($page, $size) use ($app, $source) {
                    $pkQuoted = $app->container->db->quote($app->managers->items->getEntity()->getPk());

                    return $app->managers->items
                        ->setColumns([
                            new Expression('GROUP_CONCAT(' . $pkQuoted . ') AS ' . $pkQuoted),
                        ])
                        ->setWhere([
                            'import_source_id' => $source->getId(),
                        ])
                        ->setGroups([
                            'category_id',
                            'image',
                        ])
                        ->setHavings([
                            new Expression('COUNT(*) > 1'),
                        ])
                        ->setOffset(($page - 1) * $size)
                        ->setLimit($size)
                        ->getArrays();
                })
                ->setFnDo(function ($rows) use ($app, $attrUtil, &$aff) {
                    /** @var App $app */
                    $pk = $app->managers->items->getEntity()->getPk();

                    foreach ($rows as $row) {
                        $pkValues = explode(',', $row[$pk]);
                        $pkToKeep = (int)min($pkValues);
                        $pkValues = array_diff($pkValues, [$pkToKeep]);

                        $insert = [];
                        $delete = [$pk => []];
                        $mva = [];

                        foreach ($pkValues as $pkValue) {
                            $pkValue = (int)$pkValue;

                            $insert[] = [
                                'id_from' => $pkValue,
                                'id_to' => $pkToKeep
                            ];

                            $delete[$pk][] = $pkValue;
                            $mva[$pkValue] = $pkToKeep;
                        }

                        $aff += $app->managers->itemRedirects->insertMany($insert);
                        $aff += $app->managers->items->deleteMany($delete);
                        $aff += $attrUtil->doTransferMvaValues($mva);
                    }
                })
                ->run();
        }

        return $aff;
    }
}