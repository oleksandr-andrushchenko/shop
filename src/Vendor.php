<?php

namespace SNOWGIRL_SHOP;

use SNOWGIRL_CORE\AbstractApp;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Entity\Vendor as VendorEntity;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_CORE\HtmlParser;
use SNOWGIRL_SHOP\Http\HttpApp;
use Throwable;

abstract class Vendor
{
    /**
     * @var AbstractApp|HttpApp|ConsoleApp
     */
    protected $app;

    protected $entity;

    public function __construct(AbstractApp $app, VendorEntity $entity)
    {
        $this->app = $app;
        $this->entity = $entity;
    }

    abstract public function getBuySelector(): ?string;

    public function getItemTargetLink(Item $item): ?string
    {
        return $this->app->managers->items->getTargetLink($item);
    }

    public function checkRealIsInStock(Item $item): ?bool
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
        } catch (Throwable $e) {
            $this->app->container->logger->error($e);
        }

        return false;
    }
}