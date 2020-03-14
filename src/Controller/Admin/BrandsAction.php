<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Brand;

class BrandsAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->request->redirectToRoute('admin', [
            'action' => 'database',
            'table' => Brand::getTable()
        ]);
    }
}