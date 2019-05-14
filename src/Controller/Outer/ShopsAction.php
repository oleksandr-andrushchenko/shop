<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/15/19
 * Time: 12:18 AM
 */

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Controller\Outer\ProcessTypicalPageTrait;
use SNOWGIRL_SHOP\App\Web as App;

class ShopsAction
{
    use PrepareServicesTrait;
    use ProcessTypicalPageTrait;

    /**
     * @param App $app
     *
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $view = $this->processTypicalPage($app, 'vendors', [
            'meta_title' => 'Магазины',
            'meta_description' => 'Магазины, предложения которых представлены в нашем каталоге. Наши партнеры',
            'meta_keywords' => 'Магазины,поставщики,партнеры',
            'h1' => 'Магазины',
            'description' => 'Поставщики и партнеры'
        ]);

        $view->getContent()->addParams([
            'items' => $items = $app->managers->vendors->getNonEmptyGroupedByFirstCharObjects(),
            'chars' => array_keys($items)
        ]);

        $app->response->setHTML(200, $view);
    }
}