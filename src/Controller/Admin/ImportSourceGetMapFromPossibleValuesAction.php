<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 9:52 PM
 */

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;

class ImportSourceGetMapFromPossibleValuesAction
{
    use PrepareServicesTrait;

    /**
     * @todo check permissions...
     * @param App $app
     *
     * @throws \SNOWGIRL_CORE\Exception\HTTP\Forbidden
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$id = $app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        if (!$column = $app->request->get('column')) {
            throw (new BadRequest)->setInvalidParam('column');
        }

        $source = $app->managers->sources->find($id);

        $info = $app->managers->sources->getImport($source)->getFileColumnValuesInfo($column);

        if ($notLessThan = $app->request->get('not_less_than', false)) {
            $info = array_filter($info, function ($item) use ($notLessThan) {
                return $item['total'] >= $notLessThan;
            });
        }

//        if ($app->request->get('is_items', true)) {
        //@todo process is_items param...
//        }

        $app->response->setJSON(200, $info);
    }
}