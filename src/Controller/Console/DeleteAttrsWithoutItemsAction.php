<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

/**
 * Class DeleteAttrsWithoutItemsAction
 * @deprecated VERY dangerous command - better to use DeleteItemsWithBadAttrsAction
 * @package SNOWGIRL_SHOP\Controller\Console
 */
class DeleteAttrsWithoutItemsAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws NotFoundHttpException
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

        $mysql = $app->container->mysql;

//        $itemPk = $app->managers->items->getEntity()->getPk();
        $itemTable = $app->managers->items->getEntity()->getTable();

        foreach ($app->managers->catalog->getSvaPkToTable() as $pk => $table) {
            if (in_array($table, [
                $app->managers->categories->getEntity()->getTable(),
                $app->managers->brands->getEntity()->getTable(),
            ])) {
                continue;
            }

            $aff += $mysql->req(implode(' ', [
                'DELETE ' . $mysql->quote('a'),
                'FROM ' . $mysql->quote($table) . ' ' . $mysql->quote('a'),
                'LEFT JOIN ' . $mysql->quote($itemTable) . ' ' . $mysql->quote('i') . ' USING (' . $mysql->quote($pk) . ')',
                'WHERE ' . $mysql->quote($pk, 'i') . ' IS NULL'
            ]))->affectedRows();
        }

        return $aff;
    }

    private function deleteMvaWithoutItems(App $app): int
    {
        $aff = 0;

        $mysql = $app->container->db;

        foreach ($app->managers->catalog->getMvaPkToTable() as $pk => $table) {
            $aff += $mysql->req(implode(' ', [
                'DELETE ' . $mysql->quote('a'),
                'FROM ' . $mysql->quote($table) . ' ' . $mysql->quote('a'),
                'LEFT JOIN ' . $mysql->quote('item_' . $table) . ' ' . $mysql->quote('ia') . ' USING (' . $mysql->quote($pk) . ')',
                'WHERE ' . $mysql->quote($pk, 'ia') . ' IS NULL'
            ]))->affectedRows();
        }

        return $aff;
    }
}