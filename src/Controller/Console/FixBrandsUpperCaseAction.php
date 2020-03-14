<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_SHOP\App\Console as App;

class FixBrandsUpperCaseAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $aff = 0;

        /** @var Brand $item */
        foreach ($app->managers->brands->clear()->getObjects() as $item) {
            $tmp = ucwords($item->getName());

            if ($tmp != $item->getName()) {
                $item->setName($tmp);

                if ($app->managers->brands->updateOne($item)) {
                    $aff++;
                }
            }
        }

        $app->response->setBody("DONE: {$aff}");
    }
}