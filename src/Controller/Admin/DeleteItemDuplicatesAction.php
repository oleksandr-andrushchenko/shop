<?php

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Controller\Admin\ExecTrait;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_SHOP\RBAC;

class DeleteItemDuplicatesAction
{
    use PrepareServicesTrait;
    use ExecTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $app->rbac->checkPerm(RBAC::PERM_ALL);

        self::_exec($app, 'Дубляжи предложений удалены!', function (App $app) {
            /** @var ImportSource[] $sources */
            $sources = $app->managers->sources->clear()
                ->setColumns(ImportSource::getPk())
                ->getObjects();

            $aff = 0;

            foreach ($sources as $source) {
                $aff += $app->utils->import->doDeleteImportSourceItemsDuplicates($source);
            }

            return 'Удалено: ' . $aff;
        });
    }
}