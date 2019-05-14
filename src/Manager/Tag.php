<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/5/17
 * Time: 4:45 PM
 */
namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_SHOP\Manager\Item\Attr;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_SHOP\Entity\Tag as TagEntity;

/**
 * Class Tag
 * @method Tag clear()
 * @method Tag setLimit($limit)
 * @method TagEntity find($id)
 * @method TagEntity[] findMany(array $id)
 * @package SNOWGIRL_SHOP\Manager
 */
class Tag extends Attr
{
    protected function onDeleted(Entity $entity)
    {
        /** @var TagEntity $entity */

        $output = parent::onDeleted($entity);

        $id = $entity->getId();
        $manager = $this->app->managers->sources;

        foreach ($manager->getObjects() as $source) {
            $mapping = $source->getFileMapping(true);

            foreach ($mapping as $dbColumn => $mapSettings) {
                if (isset($mapSettings['modify']) && is_array($mapSettings['modify'])) {
                    foreach ($mapSettings['modify'] as $fileValue => $modifySettings) {
                        if (in_array($id, $modifySettings['tags'])) {
                            $mapping[$dbColumn]['modify'][$fileValue]['tags'] = array_diff($modifySettings['tags'], [$id]);
                        }
                    }
                }
            }

            $source->setFileMapping($mapping);
            $manager->updateOne($source);
        }

        return $output;
    }
}