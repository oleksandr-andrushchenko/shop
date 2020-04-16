<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;
use SNOWGIRL_SHOP\Entity\Brand;

class FixBrandsUpperCaseAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     *
     * @throws \SNOWGIRL_CORE\Entity\EntityException
     * @throws \SNOWGIRL_CORE\Http\Exception\NotFoundHttpException
     */
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

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            "DONE: {$aff}",
        ]));
    }
}