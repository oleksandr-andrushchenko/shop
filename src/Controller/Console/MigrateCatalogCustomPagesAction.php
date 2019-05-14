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

class MigrateCatalogCustomPagesAction
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

        $app->response->setBody(implode("\r\n", [
            __CLASS__,
            $app->utils->catalog->doMigrateCatalogToCustom() ? 'DONE' : 'FAILED'
        ]));
    }
}