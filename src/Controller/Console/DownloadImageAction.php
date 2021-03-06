<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Image;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;

class DownloadImageAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws \SNOWGIRL_CORE\Http\Exception\NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$target = $app->request->get('param_1')) {
            throw (new BadRequestHttpException)->setInvalidParam('target');
        }

        $hash = $app->request->get('param_2', null);
        $error = null;

//        profile('wget', function () use ($app, $target, $hash) {
//            for ($i = 0; $i < 10; $i++) {
//                $error = null;
//                $app->images->downloadWithWget($target, $hash . $i, $error);
//            }
//        });
//
//        profile('curl', function () use ($app, $target, $hash) {
//            for ($i = 10; $i < 20; $i++) {
//                $error = null;
//                $app->images->downloadWithCurl($target, $hash . $i, $error);
//            }
//        });

        $image = $app->images->downloadWithWget($target, $hash, $error);

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            $image ? ('DONE: ' . $image) : ('FAILED: ' . $error),
        ]));
    }
}