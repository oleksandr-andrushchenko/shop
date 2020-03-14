<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\OutputTrait;
use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Helper\WalkChunk;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Query;
use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_SHOP\App\Console as App;
use SNOWGIRL_SHOP\Manager\Item\Attr;

class DeleteItemsExtraMvaValuesAction
{
    use PrepareServicesTrait;
    use OutputTrait;

    const MVA_VALUES_LIMIT = 3;

    /**
     * @todo sync with Import::walkImport()
     *
     * @param App $app
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        (new DeleteItemsNonExistingMvaAction)($app);

        $aff = 0;

        $aff += $this->processMvaValues($app);

        $app->response->addToBody(implode("\r\n", [
            "\r\n",
            __CLASS__,
            $app->response->setBody(is_int($aff) ? "DONE: {$aff}" : 'FAILED')
        ]));
    }

    private function processMvaValues(App $app): int
    {
        $aff = 0;

        $db = $app->container->db;

        $itemPk = $app->managers->items->getEntity()->getPk();

        foreach ($app->managers->catalog->getMvaComponents() as $class) {
            /** @var ItemAttrManager $manager */
            $manager = $app->managers->getByEntityClass($class);
            $table = $manager->getEntity()->getTable();
            $pk = $manager->getEntity()->getPk();

            $affTmp = $db->deleteFromEachGroup('item_' . $table, $itemPk, self::MVA_VALUES_LIMIT, null, $pk, true);

            $this->output($affTmp . ' deleted from item_' . $table . ' [more then ' . self::MVA_VALUES_LIMIT . ' values]', $app);
            $aff += $affTmp;
        }

        return $aff;
    }
}