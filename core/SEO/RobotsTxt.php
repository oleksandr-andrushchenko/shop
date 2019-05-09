<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 3/3/18
 * Time: 1:59 PM
 */

namespace SNOWGIRL_SHOP\SEO;

use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Page\Catalog;

/**
 * Class RobotsTxt
 * @package SNOWGIRL_SHOP\SEO
 */
class RobotsTxt extends \SNOWGIRL_CORE\SEO\RobotsTxt
{
    protected function getDisallows()
    {
        return array_merge(parent::getDisallows(), array_map(function ($param) {
            return '/*?' . $param . '*';
        }, URI::DEFINED_PARAMS), [
            '/*?' . URI::EVEN_NOT_STANDARD_PER_PAGE . '*',
            '/buy*',
            '/go*',
            '/get-catalog-filters-types-view*',
            '/get-catalog-filters-tags-view*',
            '/get-catalog-filters-brands-view*',
            '/get-catalog-filters-seasons-view*',
            '/get-catalog-filters-colors-view*',
            '/get-catalog-filters-prices-view*',
            '/get-catalog-filters-materials-view*',
            '/get-catalog-filters-countries-view*',
            '/get-catalog-filters-sizes-view*',
            '/get-catalog-filters-vendors-view*',
            '/get-catalog-items*',
            '/check-item-is-in-stock*',
            '/cart*',
        ]);
    }

    protected function getCleanParams()
    {
        return array_merge(parent::getCleanParams(), Catalog::getComponentsPKs(), URI::DEFINED_PARAMS, [
            URI::EVEN_NOT_STANDARD_PER_PAGE
        ]);
    }
}