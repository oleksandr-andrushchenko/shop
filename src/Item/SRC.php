<?php

namespace SNOWGIRL_SHOP\Item;

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

    public static function checkId($id, Managers $managers): ?Item
    {
        return self::getById($id, $managers);
    }

    private static function getById(int $id, Managers $managers): ?Item
    {
        return $managers->items->find($id);
    }

    public function getItem(): ?Item
    {
        if (null === $this->item) {
            $tmp = self::getById($this->uri->get(URI::ID), $this->uri->getApp()->managers);
            $this->item = null === $tmp ? false : $tmp;
        } elseif (false === $this->item) {
            return null;
        }

        return $this->item;
    }
}