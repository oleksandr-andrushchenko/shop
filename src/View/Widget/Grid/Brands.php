<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 12/10/17
 * Time: 1:25 PM
 */
namespace SNOWGIRL_SHOP\View\Widget\Grid;

use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\View\Widget;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Brand;

/**
 * Class Brands
 * @package SNOWGIRL_SHOP\View\Widget\Grid
 */
class Brands extends Widget
{
    protected $items = [];
    protected $padding;
    /** @var URI */
    protected $uri;

    protected function makeParams(array $params = [])
    {
        if (isset($params['uri']) && !$params['uri'] instanceof URI) {
            throw new Exception('invalid "uri" param');
        }

        return $params;
    }

    protected function stringifyPrepare()
    {
        $this->addDomClass('row');

        if ($this->padding) {
            $this->addDomClass('p' . $this->padding);
        }

        return parent::stringifyPrepare();
    }

    protected function addScripts()
    {
        return $this->addCssScript('@snowgirl-shop/widget/grid.brands.css');
    }

    protected function getLink($brand)
    {
        /** @var Brand $brand */
        if ($this->uri) {
            return $this->uri->copy()
                ->set('brand_id', $brand->getId())
                ->output(URI::OUTPUT_DEFINED_SAFE);
        }

        return $this->app->managers->brands->getLink($brand);
    }
}