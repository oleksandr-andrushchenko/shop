<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 12/18/17
 * Time: 2:27 PM
 */
namespace SNOWGIRL_SHOP;

use SNOWGIRL_SHOP\Entity\Vendor as VendorEntity;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_CORE\HtmlParser;

/**
 * Class Vendor
 * @package SNOWGIRL_SHOP
 */
abstract class Vendor
{
    /** @var App */
    protected $app;

    protected $entity;

    public function __construct(App $app, VendorEntity $entity)
    {
        $this->app = $app;
        $this->entity = $entity;
    }

    abstract public function getBuySelector();

    public function getItemTargetLink(Item $item)
    {
        return $this->app->managers->items->getTargetLink($item);
    }

    public function checkRealIsInStock(Item $item)
    {
        if (!$selector = $this->getBuySelector()) {
            return null;
        }

        if (!$link = $this->getItemTargetLink($item)) {
            return null;
        }

        try {
            if (count(HtmlParser::factoryByLink($link)->query($selector)) > 0) {
                return true;
            }
        } catch (\Exception $ex) {
            $this->app->services->logger->makeException($ex);
        }

        return false;
    }
}