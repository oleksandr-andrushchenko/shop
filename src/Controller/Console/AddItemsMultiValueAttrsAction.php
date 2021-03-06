<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;
use SNOWGIRL_SHOP\Item\FixWhere;

class AddItemsMultiValueAttrsAction
{
    use PrepareServicesTrait;

    /**
     * #1 attr_tables - string[]: comma separated attributes table names
     * #1 created_at_from_delta - int: created_at > time - delta
     * #2 created_at_to_delta - int: created_at < time - delta
     * #3 updated_at_from_delta - int: updated_at > time - delta
     * #4 updated_at_to_delta - int: updated_at < time - delta
     * #5 import_sources - int[]: comma separated ids
     * #6 or_between_created_and_updated - bool: "or" or "and" between created and updated clauses
     *
     * @param App $app
     *
     * @throws NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $output = $app->utils->attrs->doAddMvaByInclusions(
            (new FixWhere($app))
                ->setCreatedAtFrom($app->request->get('param_2'))
                ->setCreatedAtTo($app->request->get('param_3'))
                ->setUpdatedAtFrom($app->request->get('param_4'))
                ->setUpdatedAtTo($app->request->get('param_5'))
                ->setSources(array_map('trim', explode(',', $app->request->get('param_6'))))
                ->setOrBetweenCreatedAndUpdated($app->request->get('param_7')),
            array_map('trim', explode(',', $app->request->get('param_1', '')))
        );

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            $output ? 'DONE' : 'FAILED',
        ]));
    }
}