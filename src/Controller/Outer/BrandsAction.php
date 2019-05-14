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
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_SHOP\Catalog\URI;

class BrandsAction
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

        $view = $this->processTypicalPage($app, 'brands', [
            'meta_title' => 'Бренды',
            'meta_description' => 'Список брендов представленных на нашем сайте',
            'meta_keywords' => 'Бренды,производители,женские',
            'h1' => 'Бренды',
            'description' => 'Популярные и модные женские бренды'
        ]);

        $view->getContent()->addParams([
            'popularItemsGrid' => $app->views->brands([
                'uri' => new URI,
                'items' => $app->managers->brands->clear()
                    ->setWhere(new Expr('`image` IS NOT NULL'))
                    ->setOrders(['rating' => SORT_DESC])
                    ->setLimit(36)
                    ->getObjects()
            ], $view),
            //@todo create universal search widget (like in header... and implement...)
//            'itemsSearch' => '',
            'groupPerPageSize' => $groupPerPageSize = 21,
            'items' => $items = $app->managers->brands->clear()
                ->getNonEmptyGroupedByFirstCharObjects($groupPerPageSize),
            'chars' => array_keys($items)
        ]);

        $app->response->setHTML(200, $view);
    }
}