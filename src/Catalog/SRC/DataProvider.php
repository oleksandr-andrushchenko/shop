<?php

namespace SNOWGIRL_SHOP\Catalog\SRC;

use SNOWGIRL_SHOP\Catalog\SRC;

abstract class DataProvider
{
    protected $src;

    public function __construct(SRC $src)
    {
        $this->src = $src;
    }

    abstract public function getItemsAttrs(): array;

    abstract public function getWhere(bool $raw = false): array;

    abstract public function getOrder(bool $cache = false): array;

    abstract public function getTotalCount(): int;
}