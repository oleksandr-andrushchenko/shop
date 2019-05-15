<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 3/30/18
 * Time: 6:00 PM
 */

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Helper\WalkChunk;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_CORE\App;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;

/**
 * Class Import
 * @property App app
 * @package SNOWGIRL_SHOP\Util
 */
class Import extends Util
{
    public function doFixImportSourceFileMappingsModifyTags()
    {
        $aff = 0;

        foreach ($this->app->managers->sources->getObjects() as $source) {
            $mapping = $source->getFileMapping(true);

            foreach ($mapping as $dbColumn => $mappingOptions) {
                if (isset($mapping[$dbColumn]['modify']) && is_array($mapping[$dbColumn]['modify'])) {
                    foreach ($mapping[$dbColumn]['modify'] as $from => $to) {
                        if (!is_array($to)) {
                            $mapping[$dbColumn]['modify'][$from] = ['value' => $to, 'tags' => []];
                        }
                    }
                }

                $source->setFileMapping($mapping);

                if ($this->app->managers->sources->updateOne($source)) {
                    $this->output(implode(' ', [
                        'source\'s "' . $source->getName() . '"[' . $source->getId() . ']',
                        'file mappings "' . $dbColumn . '" modifiers updated: tags added'
                    ]));
                    $aff++;
                }
            }
        }

        $this->output('DONE[aff=' . $aff . ']');

        return true;
    }

    /**
     * @param ImportSource $importSource
     * @return bool
     */
    public function doDeleteImportSourceItemsDuplicates(ImportSource $importSource)
    {
        $db = $this->app->services->rdbms;

        $query = new Query(['params' => []]);
        $query->text = implode(' ', [
            'SELECT COUNT(*) AS ' . $db->quote('cnt') . ',',
            'GROUP_CONCAT(' . $db->quote(Item::getPk()) . ' SEPARATOR \',\') AS ' . $db->quote(Item::getPk()) . ',',
            'GROUP_CONCAT(' . $db->quote('is_in_stock') . ' SEPARATOR \',\')  AS ' . $db->quote('is_in_stock'),
            $db->makeFromSQL(Item::getTable()),
            $db->makeWhereSQL(['import_source_id' => $importSource->getId()], $query->params),
            $db->makeGroupSQL('image', $query->params),
            $db->makeHavingSQL(new Expr($db->quote('cnt') . ' > ?', 1), $query->params)
        ]);

        $tmp = $db->req($query)->reqToArrays();

        $copies = [];

        foreach ($tmp as $item) {
            $ids = explode(',', $item[Item::getPk()]);
            $avs = explode(',', $item['is_in_stock']);

            //left first item only
            unset($ids[(int)array_search(1, $avs)]);

            $copies += array_merge($copies, $ids);
        }

        //delete all copies
        (new WalkChunk(1000))
            ->setFnGet(function ($page, $size) use ($copies) {
                return array_slice($copies, ($page - 1) * $size, $size);
            })
            ->setFnDo(function ($items) use ($db) {
                $db->deleteMany(Item::getTable(), [
                    Item::getPk() => $items
                ]);
            })
            ->run();

        return count($copies);
    }
}