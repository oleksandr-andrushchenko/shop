<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class DeleteAttrsWithoutItemsAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws \SNOWGIRL_CORE\Http\Exception\NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $aff = 0;

        $aff += $this->deleteSvaWithoutItems($app);
        $aff += $this->deleteMvaWithoutItems($app);

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            "DONE: {$aff}",
        ]));
    }

    private function deleteSvaWithoutItems(App $app): int
    {
        $aff = 0;

        $db = $app->container->db;

//        $itemPk = $app->managers->items->getEntity()->getPk();
        $itemTable = $app->managers->items->getEntity()->getTable();

        foreach ($app->managers->catalog->getSvaPkToTable() as $pk => $table) {
            if (in_array($table, [
                $app->managers->categories->getEntity()->getTable()
            ])) {
                continue;
            }

            $aff += $db->req(implode(' ', [
                'DELETE ' . $db->quote('a'),
                'FROM ' . $db->quote($table) . ' ' . $db->quote('a'),
                'LEFT JOIN ' . $db->quote($itemTable) . ' ' . $db->quote('i') . ' USING (' . $db->quote($pk) . ')',
                'WHERE ' . $db->quote($pk, 'i') . ' IS NULL'
            ]))->affectedRows();
        }

        return $aff;
    }

    private function deleteMvaWithoutItems(App $app): int
    {
        $aff = 0;

        $db = $app->container->db;

        foreach ($app->managers->catalog->getMvaPkToTable() as $pk => $table) {
            $aff += $db->req(implode(' ', [
                'DELETE ' . $db->quote('a'),
                'FROM ' . $db->quote($table) . ' ' . $db->quote('a'),
                'LEFT JOIN ' . $db->quote('item_' . $table) . ' ' . $db->quote('ia') . ' USING (' . $db->quote($pk) . ')',
                'WHERE ' . $db->quote($pk, 'ia') . ' IS NULL'
            ]))->affectedRows();
        }

        return $aff;
    }
}