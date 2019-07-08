<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\App\Console as App;
use SNOWGIRL_SHOP\Item\FixWhere;

class FixItemsCategoriesAction
{
    use PrepareServicesTrait;

    /**
     * #1 created_at_from_delta - int: created_at > time - delta
     * #2 created_at_to_delta - int: created_at < time - delta
     * #3 updated_at_from_delta - int: updated_at > time - delta
     * #4 updated_at_to_delta - int: updated_at < time - delta
     * #5 import_sources - int[]: comma separated ids
     * #6 or_between_created_and_updated - bool: "or" or "and" between created and updated clauses
     *
     * @param App $app
     *
     * @throws \SNOWGIRL_CORE\Exception\HTTP\NotFound
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $output = $app->utils->items->doFixItemsCategories(
            (new FixWhere($app))
                ->setCreatedAtFrom($app->request->get('param_1'))
                ->setCreatedAtTo($app->request->get('param_2'))
                ->setUpdatedAtFrom($app->request->get('param_3'))
                ->setUpdatedAtTo($app->request->get('param_4'))
                ->setSources(array_map('trim', explode(',', $app->request->get('param_5'))))
                ->setOrBetweenCreatedAndUpdated($app->request->get('param_6'))

        );

        $app->response->setBody($output ? 'DONE' : 'FAILED');
    }
}