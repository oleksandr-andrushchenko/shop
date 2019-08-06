<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Image;
use SNOWGIRL_SHOP\App\Console as App;

class TestAsosImageAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $first = 'http://images.asos-media.com/inv/media/6/5/1/2/9602156/blue/image1xxl.jpg';
        $error = null;

        if ($image = Image::download($first, null, false, $error)) {
            $app->response->setBody('DONE: ' . $image);
        } else {
            $app->response->setBody('FAILED: ' . $error);
        }
    }
}