<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 12/16/17
 * Time: 6:21 PM
 */

namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Entity\Stock as StockEntity;

/**
 * Class Stock
 * @method StockEntity find($id)
 * @package SNOWGIRL_SHOP\Manager
 */
class Stock extends Manager implements GoLinkBuilderInterface
{
    public const CACHE_STOCK_PAGE = 'stock-page';

    protected $useMasterAppIfExists = false;

    public function onInserted(Entity $entity)
    {
        /** @var StockEntity $entity */

        $output = parent::onInserted($entity);

        $output = $output && $this->app->services->mcms->delete(self::CACHE_STOCK_PAGE);

        return $output;
    }

    public function getGoLink(Entity $entity, $source = null)
    {
        return $this->app->router->makeLink('default', [
            'action' => 'go',
            'type' => 'stock',
            'id' => $entity->getId(),
            'source' => $source
        ]);
    }
}