<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 11/2/16
 * Time: 8:56 PM
 */

namespace SNOWGIRL_SHOP\Manager\Import;

use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_SHOP\App;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Service\Rdbms;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSourceEntity;
use SNOWGIRL_SHOP\Entity\Tag;
use SNOWGIRL_SHOP\Import;
use SNOWGIRL_SHOP\Entity\Vendor as VendorEntity;
use SNOWGIRL_CORE\Helper\Classes;

/**
 * Class Source
 * @property ImportSourceEntity $entity
 * @method static ImportSourceEntity getItem($id)
 * @method ImportSourceEntity find($id)
 * @method static Source factory($app)
 * @property App app
 * @method ImportSourceEntity[] getObjects($idAsKeyOrKey = null)
 * @method Source clear()
 * @method Source setWhat($what)
 * @package SNOWGIRL_SHOP\Manager\Import
 */
class Source extends Manager
{
    public function onInsert(Entity $entity)
    {
        /** @var ImportSourceEntity $entity */

        $output = parent::onInsert($entity);

        if (!$entity->issetAttr('file_filter')) {
            $entity->setFileFilter([]);
        }

        if (!$entity->issetAttr('file_mapping')) {
            $entity->setFileMapping([]);
        }

        return $output;
    }

    public function updateFileFilter(ImportSourceEntity $source, array $input)
    {
        $tmp = [];

        foreach ($input as $fileColumn => $filter) {
            $tmp[$fileColumn] = [];

            foreach (['equal', 'not_equal'] as $type) {
                if (array_key_exists($type, $filter) && '' != $filter[$type]) {
                    $tmp[$fileColumn][$type] = self::_explode($filter[$type]);
                }
            }

            if (!$tmp[$fileColumn]) {
                unset($tmp[$fileColumn]);
            }
        }

        $source->setFileFilter($tmp);
        $tmp = $this->updateOne($source);

        return $tmp;
    }

    /**
     * @param ImportSourceEntity $source
     * @param array $input
     * @return array|bool|null
     */
    public function updateFileMapping(ImportSourceEntity $source, array $input)
    {
        $tmp = [];

        foreach ($input as $dbColumn => $map) {
            if (array_key_exists('value', $map) && $map['value']) {
                //custom db-column value
                $tmp[$dbColumn] = ['value' => $map['value']];
            }

            if (array_key_exists('column', $map) && $map['column']) {
                //db-column to file-column modifiers
                if (!isset($tmp[$dbColumn])) {
                    $tmp[$dbColumn] = [];
                }

                $tmp[$dbColumn]['column'] = $map['column'];

                if (array_key_exists('modify_from', $map) && array_key_exists('modify_to', $map) && count($map['modify_from']) == count($map['modify_to'])) {
                    $modify = [];

                    foreach ($map['modify_from'] as $k => $v) {
                        if (mb_strlen($v)) {
                            $modify[$v] = [
                                'value' => $map['modify_to'][$k] ?: null,
                                'tags' => array_map(function ($v) {
                                    return (int)$v;
                                }, isset($map['tags']) && isset($map['tags'][$v]) ? (array)$map['tags'][$v] : [])
                            ];

                            if (isset($map['is_sport']) && isset($map['is_sport'][$v]) && 1 == $map['is_sport'][$v]) {
                                $modify[$v][] = 'is_sport';
                            }

                            if (isset($map['is_size_plus']) && isset($map['is_size_plus'][$v]) && 1 == $map['is_size_plus'][$v]) {
                                $modify[$v][] = 'is_size_plus';
                            }
                        }
                    }

                    $tmp[$dbColumn]['modify'] = $modify;

                    if (count($modify) && array_key_exists('modify_only', $map)) {
                        unset($map['modify_only']);
                        $tmp[$dbColumn][] = 'modify_only';
                    }
                }
            }
        }

        $source->setFileMapping($tmp);
        $tmp = $this->updateOne($source);

        return $tmp;
    }

    public function getFileMappingModifyTags(ImportSourceEntity $source)
    {
        $output = [];

        foreach ($source->getFileMapping(true) as $dbColumn => $settings) {
            if (isset($settings['modify']) && is_array($settings['modify'])) {
                foreach ($settings['modify'] as $fileValue => $map) {
                    if (isset($map['tags']) && is_array($map['tags'])) {
                        $output = array_merge($output, $map['tags']);
                    }
                }
            }
        }

        $output = array_filter(array_unique($output), function ($taId) {
            return 0 < $taId;
        });

        if ($output) {
            $tags = array_filter($this->app->managers->tags->findMany($output), function ($tag) {
                return $tag instanceof Tag;
            });

            return array_map(function ($tag) {
                /** @var Tag $tag */
                return $tag->getName();
            }, $tags);
        }

        return $output;
    }

    public function deleteItems(ImportSourceEntity $source)
    {
        return $this->app->services->rdbms->makeTransaction(function () use ($source) {
            $where = [
                'vendor_id' => $source->getVendorId()
            ];

            $items = $this->app->services->rdbms->selectMany(Item::getTable(), new Query([
                'columns' => 'image',
                'where' => $where
            ]));

            $this->app->services->rdbms->deleteMany(Item::getTable(), new Query(['where' => $where]));

            $this->app->services->rdbms->on(Rdbms::EVENT_COMPLETE, function () use ($items) {
                foreach ($items as $item) {
                    $this->app->images->get($item['image'])->delete();
                }

                //@todo flush cache
            });

            return true;
        });
    }

    public static function _explode($input, $delimiter = ',')
    {
        $input = explode($delimiter, $input);

        array_walk($input, function (&$i) {
            $i = trim($i);
        });

        $input = array_filter($input, function ($i) {
            return $i !== '';
        });

        return $input;
    }

    public function getLink(Entity $entity, array $params = [], $domain = false)
    {
        /** @var ImportSourceEntity $entity */

        return $this->app->router->makeLink('admin', array_merge($params, [
            'action' => 'import-source',
            'id' => $entity->getId()
        ]), $domain);
    }

    /**
     * @param ImportSourceEntity $source
     * @return Entity|VendorEntity
     */
    public function getVendor(ImportSourceEntity $source)
    {
        return $this->getLinked($source, 'vendor_id');
    }

    /**
     * @param ImportSourceEntity $source
     * @return Import
     */
    public function getImport(ImportSourceEntity $source)
    {
        if ($class = $source->getClassName()) {
            $class = Classes::aliasToReal($this->app, $class, 'Import');
        } else {
            $class = Import::class;
        }

        return new $class($this->app, $source);
    }

    public function getImportClasses($withAliases = false, $whole = false)
    {
        return Classes::getInDir($this->app, 'Import', ['@snowgirl-shop', '@app'], $withAliases, $whole);
    }
}