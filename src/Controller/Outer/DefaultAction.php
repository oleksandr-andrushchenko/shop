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

class DefaultAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     *
     * @return mixed
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        return (new CatalogAction)($app);
    }
}