<?php

namespace SNOWGIRL_SHOP\View;

use SNOWGIRL_SHOP\View\Widget\Grid\Categories;
use SNOWGIRL_SHOP\View\Widget\Grid\Brands;
use SNOWGIRL_SHOP\View\Widget\Grid\Items;

use SNOWGIRL_SHOP\View\Widget\Form\Order as OrderForm;

/**
 * Class Builder
 * @method Categories categories($params = [], $parent = null)
 * @method Brands brands($params = [], $parent = null)
 * @method Items items($params = [], $parent = null)
 * @method OrderForm orderForm(array $params = [], $parent = null)
 *
 * @package SNOWGIRL_SHOP\View
 */
class Builder extends \SNOWGIRL_CORE\View\Builder
{
    public function _call($fn, array $args)
    {
        switch ($fn) {
            case 'categories':
                return $this->getWidget(Categories::class, $args[0] ?? [], $args[1] ?? null);
            case 'brands':
                return $this->getWidget(Brands::class, $args[0] ?? [], $args[1] ?? null);
            case 'items':
                return $this->getWidget(Items::class, $args[0] ?? [], $args[1] ?? null);
            case 'orderForm':
                return $this->getWidget(OrderForm::class, $args[0] ?? [], $args[1] ?? null);
            default:
                return parent::_call($fn, $args);
        }
    }
}