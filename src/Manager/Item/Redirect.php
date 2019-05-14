<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 9/22/18
 * Time: 5:06 PM
 */

namespace SNOWGIRL_SHOP\Manager\Item;

use SNOWGIRL_CORE\Manager;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_SHOP\Entity\Item\Redirect as RedirectEntity;

/**
 * Class Redirect
 * @package SNOWGIRL_SHOP\Manager\Item
 */
class Redirect extends Manager
{
    protected function onInserted(Entity $entity)
    {
        /** @var RedirectEntity $entity */

        $output = parent::onInserted($entity);

        $output = $output && $this->updateMany(['id_to' => $entity->getIdTo()], [
                'id_from' => $entity->getIdFrom()
            ], true);

        return $output;
    }

    /**
     * @param $id
     * @return array|null
     */
    public function getByIdFrom($id)
    {
        $tmp = $this->clear()
            ->setColumns(['id_from', 'id_to'])
            ->setWhere(['id_from' => $id])
            ->getArrays();

        if (is_array($id)) {
            $output = [];

            foreach ($tmp as $item) {
                $output[$item['id_from']] = $item['id_to'];
            }

            return $output;
        }

        if (is_array($tmp) && isset($tmp[0])) {
            return $tmp[0]['id_to'];
        }

        return null;
    }
}