<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/15/19
 * Time: 12:18 AM
 */

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Web as App;

class VendorsAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->request->redirect($app->router->makeLink('default', ['action' => 'shops']), 301);
    }
}