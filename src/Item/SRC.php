<?php

namespace SNOWGIRL_SHOP\Item;

use SNOWGIRL_CORE\App;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Manager\Builder as Managers;

class SRC
{
    /** @var App */
    protected $app;
    protected $uri;

    public function __construct(URI $uri)
    {
        $this->uri = $uri;
        $this->app = App::$instance;
    }

    /**
     * @param          $id
     * @param Managers $managers
     *
     * @return Item
     */
    public static function checkId($id, Managers $managers)
    {
        return self::getById($id, $managers);
    }

    /**
     * @param          $id
     * @param Managers $managers
     *
     * @return Item
     */
    protected static function getById($id, Managers $managers)
    {
        if (!$item = $managers->items->find($id)) {
            if ($archive = $managers->archiveItems->find($id)) {
                $item = new Item($archive->getAttrs());
                $item->set('archive', true);
            }
        }

        return $item;
    }

    protected $item;

    /**
     * @return Item
     */
    public function getItem()
    {
        if (null === $this->item) {
            $this->item = self::getById($this->uri->get(URI::ID), $this->app->managers);
        }

        return $this->item;
    }
}