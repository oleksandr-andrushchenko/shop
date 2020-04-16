<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_SHOP\Catalog\SEO;
use SNOWGIRL_SHOP\Entity\Item\Attr;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_SHOP\Console\ConsoleApp as App;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Page\Catalog;

/**
 * Example: php bin/console add-catalog-page category=10,color=10,sport,sales,size-plus
 * Should be synced with Pages::generateCatalogPages
 * Class AddPageCatalogAction
 *
 * @package SNOWGIRL_SHOP\Controller\Console
 */
class AddCatalogPageAction
{
    use PrepareServicesTrait;

    /**
     * @param App $app
     * @throws \SNOWGIRL_CORE\Entity\EntityException
     * @throws \SNOWGIRL_CORE\Http\Exception\NotFoundHttpException
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$rawWhere = $app->request->get('param_1')) {
            throw (new BadRequestHttpException)->setInvalidParam('where');
        }

        $where = $this->buildWhere($app, $rawWhere);

        /** @var Catalog $page */
        $page = (new Catalog)
            ->setName($this->buildName($app, $where))
            ->setRawAttr('uri', $this->buildUri($app, $where))
            ->setParams($this->buildParams($app, $where))
            ->setMeta($this->buildMeta($app, $where));

        if ($aff = $app->managers->catalog->insertOne($page)) {
            $app->managers->catalog->indexOne($page);
        }

        $app->response->addToBody(implode("\r\n", [
            '',
            __CLASS__,
            $aff ? 'DONE' : 'FAILED',
        ]));
    }

    protected function buildWhere(App $app, string $rawWhere): array
    {
        $output = [];

        foreach (explode(',', $rawWhere) as $expr) {
            $peaces = explode('=', trim($expr));
            $cnt = count($peaces);

            if (1 == $cnt) {
                $output[trim($peaces[0])] = 1;
            } elseif (2 == $cnt) {
                $manager = $app->managers->getByTable(trim($peaces[0]));
                $output[$manager->getEntity()->getTable()] = $manager->find((int) trim($peaces[1]));
            } else {
                throw (new BadRequestHttpException)->setInvalidParam('expr');
            }
        }

        return $output;
    }

    protected function buildName(App $app, array $where): string
    {
        $output = [];

        /** @var Attr[] $where */

        foreach ($app->managers->catalog->getComponentsOrderByDbKey() as $component) {
            foreach ($where as $tableOrType => $entityOrTrue) {
                if ($tableOrType == $component::getTable()) {
                    if ($entityOrTrue->hasAttr('name_multiply') && $tmp = $entityOrTrue->get('name_multiply')) {
                        $output[] = $tmp;
                    } else {
                        $output[] = $entityOrTrue->getName();
                    }
                }
            }
        }

        $typesTexts = SEO::getTypesToTexts();

        foreach (URI::TYPE_PARAMS as $type) {
            foreach ($where as $tableOrType => $entityOrTrue) {
                if ($tableOrType == $type) {
                    $output[] = $typesTexts[$type];
                }
            }
        }

        return implode(' ', $output);
    }

    protected function buildUri(App $app, array $where): string
    {
        $output = [];

        /** @var Attr[] $where */

        foreach (URI::getPagePath([], true) as $uri) {
            foreach ($where as $tableOrType => $entityOrTrue) {
                if ($tableOrType == $uri) {
                    if (is_object($entityOrTrue)) {
                        $output[] = $entityOrTrue->getUri();
                    } else {
                        $output[] = $tableOrType;
                    }
                }
            }
        }

        return implode('/', $output);
    }

    protected function buildParams(App $app, array $where)
    {
        $output = [];

        /** @var Attr[] $where */

        foreach ($app->managers->catalog->getComponentsOrderByDbKey() as $component) {
            foreach ($where as $tableOrType => $entityOrTrue) {
                if ($tableOrType == $component::getTable()) {
                    $output[$entityOrTrue->getPk()] = $entityOrTrue->getId();
                }
            }
        }

//        $typesTexts = SEO::getTypesToTexts();

        foreach (URI::TYPE_PARAMS as $type) {
            foreach ($where as $tableOrType => $entityOrTrue) {
                if ($tableOrType == $type) {
                    $output[$type] = 1;
                }
            }
        }

        return json_encode($output);
    }

    protected function buildMeta(App $app, array $where)
    {
        $output = [];

        $output['count'] = 0;

        return json_encode($output);
    }
}