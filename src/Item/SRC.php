<?php

namespace SNOWGIRL_SHOP\Item;

use SNOWGIRL_CORE\AbstractApp;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Manager\Builder as Managers;

class SRC
{
    private $uri;
    private $item;

    public function __construct(URI $uri)
    {
        $this->uri = $uri;
    }

    public static function checkId($id, Managers $managers): Item
    {
        return self::getById($id, $managers);
    }

    private static function getById(int $id, Managers $managers): Item
    {
        if (!$item = $managers->items->find($id)) {
            if ($archive = $managers->archiveItems->find($id)) {
                $item = new Item($archive->getAttrs());
                $item->set('archive', true);
            }
        }

        return $item;
    }

    public function getItem(): Item
    {
        if (null === $this->item) {
            $this->item = self::getById($this->uri->get(URI::ID), $this->uri->getApp()->managers);
        }

        return $this->item;
    }
}