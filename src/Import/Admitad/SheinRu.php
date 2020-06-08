<?php

namespace SNOWGIRL_SHOP\Import\Admitad;

use SNOWGIRL_SHOP\Import\Admitad;

class SheinRu extends Admitad
{
    protected $colorIndex;
    protected $sizesIndex;

    protected function before()
    {
        $this->colorIndex = isset($this->indexes['color']);
        $this->sizesIndex = isset($this->indexes['size']);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     *
     * @return array
     */
    protected function getColorsByRow($row)
    {
        if ($this->colorIndex && $color = trim($row[$this->indexes['color']])) {
            return [$color];
        }

        return parent::getColorsByRow($row);
    }

    /**
     * Returns mixed names
     *
     * @param $row
     *
     * @return array
     */
    protected function getSizesByRow($row)
    {
        if ($this->sizesIndex && $sizes = trim($row[$this->indexes['size']])) {
            return array_map('trim', explode(' ', $sizes));
        }

        return parent::getSizesByRow($row);
    }
}