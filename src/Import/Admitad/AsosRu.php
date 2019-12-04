<?php

namespace SNOWGIRL_SHOP\Import\Admitad;

use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\Import\Admitad;

class AsosRu extends Admitad
{
    protected $filters = [
        'param' => [
            'equal' => ['Female']
        ],
//        'currencyId' => [
//            'equal' => ['RUB']
//        ]
    ];

    protected $paramsIndex;
    protected $params;

    protected function before()
    {
        $this->paramsIndex = isset($this->indexes['param']);
    }

    protected function importAllMvaByRow($row, $partnerItemId = null)
    {
        if ($this->paramsIndex) {
            $this->params = Arrays::mapByKeyValueMaker(explode('|', $row[$this->indexes['param']]), function ($k, $v) {
                $tmp = explode(':', $v);
                return [trim($tmp[0]), trim($tmp[1])];
            });
        }

        parent::importAllMvaByRow($row, $partnerItemId);
    }

    protected function getSizesByRow($row)
    {
        if ($this->paramsIndex) {
            if (isset($this->params['Size'])) {
                if (in_array($this->params['Size'], ['Один размер', 'No Size'])) {
                    return ['NS'];
                }

                return array_map('trim', preg_split('/[,\/]/', $this->params['Size'], -1, PREG_SPLIT_NO_EMPTY));
            }
        }

        return parent::getSizesByRow($row);
    }

    protected function getColorsByRow($row)
    {
        if ($this->paramsIndex) {
            if (isset($this->params['Colour'])) {
                return array_map('trim', explode(',', $this->params['Colour']));
            }
        }

        return parent::getColorsByRow($row);
    }
}