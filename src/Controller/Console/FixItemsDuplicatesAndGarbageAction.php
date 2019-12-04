<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\OutputTrait;
use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Helper\WalkChunk;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_SHOP\App\Console as App;

/**
 * @todo    ...........................
 * Class FixItemsDuplicatesAndGarbageAction
 *
 * @package SNOWGIRL_SHOP\Controller\Console
 */
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

        foreach ($app->managers->sources->getObjects() as $source) {
            (new WalkChunk(1000))
                ->setFnGet(function ($page, $size) use ($app, $source) {
                    $pkQuotted = $app->storage->mysql->quote($app->managers->items->getEntity()->getPk());
                    $partnerItemIdQuotted = $app->storage->mysql->quote('partner_item_id');
                    $imageQuotted = $app->storage->mysql->quote('image');
                    $cntQuotted = $app->storage->mysql->quote('cnt');

                    return $app->managers->items
                        ->setColumns([
                            'category_id',
                            'partner_link_hash',
                            new Expr('GROUP_CONCAT(' . $pkQuotted . ') AS ' . $pkQuotted),
                            new Expr('GROUP_CONCAT(' . $partnerItemIdQuotted . ') AS ' . $partnerItemIdQuotted),
                            new Expr('GROUP_CONCAT(' . $imageQuotted . ') AS ' . $imageQuotted),
                            new Expr('COUNT(*) AS ' . $cntQuotted),
                        ])
                        ->setWhere([
                            'import_source_id' => $source->getId()
                        ])
                        ->setGroups([
                            'category_id',
                            'partner_link_hash'
                        ])
                        ->setHavings([
                            new Expr($cntQuotted . ' > 1')
                        ])
                        ->setOffset(($page - 1) * $size)
                        ->setLimit($size)
                        ->getArrays();
                })
                ->setFnDo(function ($rows) {
//                    $pk = $app->managers->items->getEntity()->getPk();
//
//                    foreach ($rows as $row) {
//                        $pkValues = explode(',', $row[$pk]);
//                        # @todo...
//                    }
                })
                ->run();
        }

        $app->response->setBody(is_int($aff) ? "DONE: {$aff}" : 'FAILED');
    }
}