<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/15/19
 * Time: 12:28 AM
 */

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_SHOP\App\Web as App;

trait GetFiltersCountsObjectTrait
{
    public function getFiltersCountsObject(App $app)
    {
        return $app->config->catalog->filters_counts([
            'tag' => 10,
            'brand' => 100,
            'country' => null,
            'vendor' => null,
            'color' => 36,
            'season' => null,
            'material' => 100,
            'size' => 100
        ]);
    }
}