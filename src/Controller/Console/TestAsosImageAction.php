<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 10:50 PM
 */

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Image;
use SNOWGIRL_SHOP\App\Console as App;

class TestAsosImageAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     *
     * @return \SNOWGIRL_CORE\Response
     * @throws \SNOWGIRL_CORE\Exception\HTTP\NotFound
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $first = 'http://images.asos-media.com/inv/media/6/5/1/2/9602156/blue/image1xxl.jpg';
        $error = null;

        if (!$image = Image::download($first, null, $error)) {
            return $app->response->setBody('FAILED: ' . $error);
        }

        $app->response->setBody('DONE: ' . $image);
    }
}