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

class ImportSourceGetMapToPossibleValuesAction
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

        $manager = $app->managers->getByEntityPk($column);
        $entity = $manager->getEntity()->getClass();

        $app->response->setJSON(200, $app->utils->attrs->getIdToName($entity));
    }
}