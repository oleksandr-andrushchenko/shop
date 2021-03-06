<?php

namespace SNOWGIRL_SHOP\Import\Admitad;

use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\Import\Admitad;

class LacosteRu extends Admitad
{
    protected function beforeWalkImport()
    {
        parent::beforeWalkImport();
        
        if ($this->paramsIndex) {
            $this->paramsCallbacks['size_id'] = function ($params) {
                if (isset($params['Size'])) {
                    if (in_array($params['Size'], ['Один размер', 'No Size'])) {
                        return ['NS'];
                    }

                    return array_map('trim', preg_split('/[,\/]/', $params['Size'], -1, PREG_SPLIT_NO_EMPTY));
                }
            };

            $this->paramsCallbacks['color_id'] = function ($params) {
                if (isset($params['Colour'])) {
                    return array_map('trim', explode(',', $params['Colour']));
                }
            };
        }
    }
}