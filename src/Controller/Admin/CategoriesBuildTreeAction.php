<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 9:52 PM
 */

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Entity\User;
use SNOWGIRL_CORE\Exception\HTTP\Forbidden;
use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;

class CategoriesBuildTreeAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     *
     * @throws Forbidden
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN, User::ROLE_MANAGER)) {
            throw new Forbidden;
        }

        try {
            if ($app->utils->categories->doBuildTreeByNames($app->request->get('delimiter', '/'), $error)) {
                $app->views->getLayout(true)->addMessage('DONE', Layout::MESSAGE_SUCCESS);
            } else {
                $app->views->getLayout(true)->addMessage('FAILED: ' . $error, Layout::MESSAGE_ERROR);
            }
        } catch (\Exception $ex) {
            $app->views->getLayout(true)->addMessage('FAILED: ' . $ex->getMessage(), Layout::MESSAGE_ERROR);
        }

        $app->request->redirect($app->request->getReferer());
    }
}