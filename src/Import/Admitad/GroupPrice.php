<?php

namespace SNOWGIRL_SHOP\Import\Admitad;

use SNOWGIRL_SHOP\Import\Admitad;

class GroupPrice extends Admitad
{
    protected function beforeReadRow($row)
    {
        if (isset($row['description'])) {
            $row['description'] = htmlspecialchars_decode($row['description']);
        }

        return $row;
    }
}