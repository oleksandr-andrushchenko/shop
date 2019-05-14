<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 10:50 PM
 */

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Console as App;

class DeleteItemsWithBadAttrsAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     *
     * @throws \SNOWGIRL_CORE\Exception\HTTP\NotFound
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $aff = $app->utils->items->doDeleteWithNonExistingCategories();
        $app->response->setBody(is_int($aff) ? "DONE[non-existing-categories]={$aff}" : 'FAILED');

        $aff = $app->utils->items->doDeleteWithNonExistingBrands();
        $app->response->setBody(is_int($aff) ? "DONE[non-existing-brands]={$aff}" : 'FAILED');
    }
}