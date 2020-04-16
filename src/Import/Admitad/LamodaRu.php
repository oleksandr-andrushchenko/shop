<?php

namespace SNOWGIRL_SHOP\Import\Admitad;

use SNOWGIRL_SHOP\Import\Admitad;

class LamodaRu extends Admitad
{
    protected $filters = [
        'categoryId' => [
            'equal' => ['женщ'],
            'not_equal' => ['муж', 'дево', 'мальч', 'детс']
        ],
        'currencyId' => [
            'equal' => ['RUB']
        ]
    ];

//    protected $defaultAllowModifyOnly = false;

    /**
     * @param $row
     *
     * @return int|null
     */
    protected function getCategoryByRow($row)
    {
        if (array_key_exists('_category_id', $row)) {
            return $row['_category_id'];
        }

        # @todo checkout && test
        $partnerItemId = $this->getPartnerItemIdByRow($row);

        if (isset($this->dbRows[$partnerItemId])) {
            return $this->dbRows[$partnerItemId]['category_id'];
        }

        $pk = 'category_id';
        $map = $this->mappings[$pk];
        $value = trim($row[$this->indexes[$this->mappings[$pk]['column']]]);

        if (array_key_exists('modify', $map) && array_key_exists($value, $modifies = $map['modify']) && $modifies[$value]['value']) {
            $this->rememberMva($partnerItemId, 'tag_id', $modifies[$value]['tags']);
            $this->sport[$partnerItemId] = in_array('is_sport', $modifies[$value]);
            $this->sizePlus[$partnerItemId] = in_array('is_size_plus', $modifies[$value]);
            return (int)$modifies[$value]['value'];
        }

        $exp = explode('/', $value);

        foreach (array_reverse($exp) as $peace) {
            if (isset($this->sva[$pk]['nameToId'][$peace])) {
                return $this->sva[$pk]['nameToId'][$peace];
            }

            foreach ($this->sva[$pk]['nameToId'] as $categoryName => $categoryId) {
                if (false !== mb_stripos($peace, $categoryName)) {
                    return $categoryId;
                }
            }
        }

        return parent::getCategoryByRow($row);
    }
}