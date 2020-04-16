<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Helper\WalkChunk;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Entity\Brand as BrandEntity;
use SNOWGIRL_SHOP\Http\HttpApp;
use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalogManager;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_SHOP\Catalog\URI\Manager as CatalogUriManager;
use SNOWGIRL_SHOP\Entity\Brand\Term as BrandTerm;
use SNOWGIRL_SHOP\Manager\Brand\Term as BrandTermManager;
use Throwable;

/**
 * Class Brand
 *
 * @property HttpApp|ConsoleApp app
 * @package SNOWGIRL_SHOP\Util
 */
class Brand extends Util
{
    /**
     * @todo if logic has changed - sync with Import::import
     */
    public function doFixBrands()
    {
        $crossAttrUri = [];

        foreach (array_diff(PageCatalogManager::getComponentsOrderByDbKey(), [BrandEntity::class]) as $component) {
            foreach ($this->app->managers->getByEntityClass($component)->clear()
                         ->setColumns('uri')
                         ->getArrays() as $item) {
                $crossAttrUri[] = $item['uri'];
            }
        }

        (new WalkChunk(1000))
            ->setFnGet(function ($page, $size) {
                return $this->app->managers->brands->clear()
                    ->setOrders([BrandEntity::getPk() => SORT_ASC])
                    ->setOffset(($page - 1) * $size)
                    ->setLimit($size)
                    ->getObjects();
            })
            ->setFnDo(function ($items) use (&$crossAttrUri) {
                foreach ($items as $item) {
                    /** @var BrandEntity $item */

                    $item->setName($name = $item->getName())
                        ->setUri($uri = $item->getUri());

                    if ($item->isAttrsChanged()) {
                        try {
                            $newUri = $item->getUri();

                            if (in_array($newUri, $crossAttrUri)) {
                                $newUri = $newUri . '-brand';
                                $crossAttrUri[] = $newUri;
                            }

                            $item->setRawAttr('uri', $newUri);

                            $this->app->managers->brands->updateOne($item);
                            $this->output($name . '[' . $uri . '] is changed -> ' . $item->getName() . '[' . $item->getUri() . ']');
                        } catch (Throwable $e) {
                            $new = $this->app->managers->brands->clear()
                                ->setWhere(['uri' => $item->getUri()])
                                ->getObject();

                            if ($new) {
                                $this->app->container->db->updateMany(Item::getTable(), [BrandEntity::getPk() => $new->getId()], [
                                    BrandEntity::getPk() => $item->getId()
                                ]);

                                $this->app->managers->brands->deleteOne($item);
                                $this->output($name . '[' . $uri . '] items are transferred[by uri] -> ' . $new->getName() . '[' . $new->getUri() . ']');
                                $this->output($name . '[' . $uri . '] is deleted');
                                continue;
                            }

                            $new = $this->app->managers->brands->clear()
                                ->setWhere(['name' => $item->getName()])
                                ->getObject();

                            if ($new) {
                                $this->app->container->db->updateMany(Item::getTable(), [BrandEntity::getPk() => $new->getId()], [
                                    BrandEntity::getPk() => $item->getId()
                                ]);

                                $this->app->managers->brands->deleteOne($item);
                                $this->output($name . '[' . $uri . '] items are transferred[by name] -> ' . $new->getName() . '[' . $new->getUri() . ']');
                                $this->output($name . '[' . $uri . '] is deleted');
                                continue;
                            }
                        }
                    }
                }
            })
            ->run();

        return true;
    }

    public function doDeleteEmptyBrands()
    {
        (new WalkChunk(1000))
            ->setFnGet(function ($page, $size) {
                return $this->app->container->db->reqToArrays(implode(' ', [
                    'SELECT ' . $this->app->container->db->quote('b') . '.*, COUNT(*) AS ' . $this->app->container->db->quote('cnt'),
                    'FROM ' . $this->app->container->db->quote(BrandEntity::getTable()) . ' AS ' . $this->app->container->db->quote('b'),
                    'INNER JOIN ' . $this->app->container->db->quote(Item::getTable()) . ' AS ' . $this->app->container->db->quote('i') . ' USING(' . $this->app->container->db->quote(BrandEntity::getPk()) . ')',
                    'GROUP BY ' . $this->app->container->db->quote(BrandEntity::getPk(), 'b'),
                    'LIMIT ' . (($page - 1) * $size) . ', ' . $size
                ]));
            })
            ->setFnDo(function ($items) {
                foreach ($items as $item) {
                    $cnt = $item['cnt'];

                    if (0 == $cnt) {
                        /** @var BrandEntity $brand */
                        $brand = $this->app->managers->brands->populateRow($item);
                        $this->app->managers->brands->deleteOne($brand);
                        $this->output($brand->getName() . '[' . $brand->getUri() . '] is deleted');
                    }
                }
            })
            ->run();

        return true;
    }

    /**
     * Params:
     * 1) source_brand_id - int - brand id (required) !should exists
     * 2) target_brand_id - int - brand id (required) !should exists
     * 3) rotate_off - 1|0 - rotate ftdbms & mcms (options, default = 1)
     *
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws Throwable
     */
    public function doTransferBrandToBrand()
    {
        if (!$sourceBrandId = (int)trim($this->app->request->get('param_1'))) {
            throw (new BadRequestHttpException)->setInvalidParam('source_brand_id');
        }

        if (!$sourceBrand = $this->app->managers->brands->find($sourceBrandId)) {
            throw (new NotFoundHttpException)->setNonExisting('source_brand');
        }

        if (!$targetBrandId = (int)trim($this->app->request->get('param_2'))) {
            throw (new BadRequestHttpException)->setInvalidParam('target_brand_id');
        }

        if (!$targetBrand = $this->app->managers->brands->find($targetBrandId)) {
            throw (new NotFoundHttpException)->setNonExisting('target_brand');
        }

        $rotate = 1 == $this->app->request->get('param_4', 0);

        $this->app->container->db->makeTransaction(function () use ($sourceBrand, $targetBrand) {
            $where = ['brand_id' => $sourceBrand->getId()];

            $this->app->managers->items->updateMany(['brand_id' => $targetBrand->getId()], $where, ['ignore'=>true]);

            (new CatalogUriManager($this->app))
                ->addRedirect($sourceBrand->getCatalogUri(), new URI([
                    'brand_id' => $targetBrand->getId()
                ]));

            $this->app->managers->get(BrandTermManager::class)->insertOne(new BrandTerm([
                'brand_id' => $targetBrand->getId(),
                'value' => $sourceBrand->getName()
            ]));

            $this->app->managers->catalog->deleteMany($where);

            $this->app->managers->brands->deleteOne($sourceBrand);
        });

        if ($rotate) {
//            $this->app->storage->elastic->flush();
            $this->app->utils->items->doIndexIndexer(1);
            //@todo...
//            $this->app->utils->catalog->doDeleteIndexer(['brand.id' => $sourceBrand->getId()]);
            $this->app->container->cache->flush();
        }

        return true;
    }
}