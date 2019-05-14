<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/14/19
 * Time: 9:52 PM
 */

namespace SNOWGIRL_SHOP\Controller\Admin;

use SNOWGIRL_CORE\Controller\Admin\ExecTrait;
use SNOWGIRL_CORE\Entity\User;
use SNOWGIRL_CORE\Exception\HTTP\Forbidden;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_CORE\Controller\Admin\PrepareServicesTrait;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;

class DeleteItemDuplicatesAction
{
    use PrepareServicesTrait;
    use ExecTrait;

    /**
     * @param App $app
     *
     * @throws Forbidden
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$app->request->getClient()->getUser()->isRole(User::ROLE_ADMIN)) {
            throw new Forbidden;
        }

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