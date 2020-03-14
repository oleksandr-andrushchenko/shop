<?php

namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_SHOP\Manager\Item\Attr;
use SNOWGIRL_SHOP\Entity\Vendor as VendorEntity;
use SNOWGIRL_SHOP\Vendor as VendorAdapter;
use SNOWGIRL_CORE\Helper\Classes;
use SNOWGIRL_SHOP\Util\Item as ItemUtil;

/**
 * Class Vendor
 *
 * @property VendorEntity $entity
 * @method static VendorEntity getItem($id)
 * @method Vendor clear()
 * @method Vendor setLimit($limit)
 * @method VendorEntity find($id)
 * @method VendorEntity[] findMany(array $id)
 * @package SNOWGIRL_SHOP\Manager
 */
class Vendor extends Attr implements GoLinkBuilderInterface
{
    public function onUpdated(Entity $entity)
    {
        /** @var VendorEntity $entity */

        $output = parent::onUpdated($entity);

        if ($entity->isAttrChanged('is_active')) {
            $util = new ItemUtil($this->app);
            $where = [$entity->getPk() => $entity->getId()];

            if ($entity->isActive()) {
                $util->doOutArchiveTransfer($where);
            } else {
                $util->doInArchiveTransfer($where);
            }
        }

        return $output;
    }

    protected function getNonEmptyCacheKey()
    {
        return $this->entity->getTable() . '-non-empty';
    }

    /**
     * @return Entity[]|VendorEntity[]
     */
    public function getNonEmptyObjects()
    {
        $key = $this->getNonEmptyCacheKey();

        if (!$this->app->container->cache->has($key, $list)) {
            $pk = $this->entity->getPk();

            $columns = new Expression('DISTINCT(' . $pk . ') AS ' . $this->app->container->db->quote($pk));

            $list = $this->app->managers->items->clear()->getColumn($pk, $columns);

            $this->app->container->cache->set($key, $list);
        }

        return $this->populateList($list);
    }

    /**
     * @return array
     */
    public function getNonEmptyGroupedByFirstCharObjects()
    {
        $output = [];

        $this->clear();

        foreach ($this->getNonEmptyObjects() as $item) {
            $char = mb_strtoupper(mb_substr($item->getName(), 0, 1));

            if (!isset($output[$char])) {
                $output[$char] = [];
            }

            $output[$char][] = $item;
        }

        ksort($output);

        return $output;
    }

    public function canCheckRealIsInStock(VendorEntity $vendor, $strict = false)
    {
//        return true;
//        return false;
        return !!$this->getAdapterClass($vendor, $strict);
    }

    /**
     * @param VendorEntity $vendor
     *
     * @return VendorAdapter|null
     */
    public function getAdapterObject(VendorEntity $vendor)
    {
        if ($class = $this->getAdapterClass($vendor, true)) {
            return new $class($this->app, $vendor);
        }

        return null;
    }

    public function getAdapterClass(VendorEntity $vendor, $strict = false)
    {
        if (!$class = $vendor->getClassName()) {
            return null;
        }

        if ($strict) {
            $class = Classes::aliasToReal($this->app, $class, 'Vendor');

            if ($this->app->loader->findFile($class)) {
                return $class;
            }

            return null;
        }

        return $class;
    }

    public function getAdapterClasses($withAliases = false, $whole = false)
    {
        return Classes::getInDir($this->app, 'Vendor', ['@shop'], $withAliases, $whole);
    }

    public function getGoLink(Entity $entity, $source = null)
    {
        return $this->app->router->makeLink('default', [
            'action' => 'go',
            'type' => 'shop',
            'id' => $entity->getId(),
            'source' => $source
        ]);
    }
}