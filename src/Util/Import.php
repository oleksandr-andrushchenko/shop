<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Helper\WalkChunk;
use SNOWGIRL_CORE\Mysql\MysqlQueryExpression;
use SNOWGIRL_CORE\Mysql\MysqlQuery;
use SNOWGIRL_CORE\Util;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_SHOP\Http\HttpApp;

/**
 * @property ConsoleApp|HttpApp app
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
                        'file mappings "' . $dbColumn . '" modifiers updated: tags added',
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
        $mysql = $this->app->container->mysql;

        $query = new MysqlQuery(['params' => []]);
        $query->text = implode(' ', [
            'SELECT COUNT(*) AS ' . $mysql->quote('cnt') . ',',
            'GROUP_CONCAT(' . $mysql->quote(Item::getPk()) . ' SEPARATOR \',\') AS ' . $mysql->quote(Item::getPk()) . ',',
            'GROUP_CONCAT(' . $mysql->quote('is_in_stock') . ' SEPARATOR \',\')  AS ' . $mysql->quote('is_in_stock'),
            $mysql->makeFromSQL(Item::getTable()),
            $mysql->makeWhereSQL(['import_source_id' => $importSource->getId()], $query->params, null, $query->placeholders),
            $mysql->makeGroupSQL('image', $query->params),
            $mysql->makeHavingSQL(new MysqlQueryExpression($mysql->quote('cnt') . ' > ?', 1), $query->params),
        ]);

        $tmp = $mysql->reqToArrays($query);

        $copies = [];

        foreach ($tmp as $item) {
            $ids = explode(',', $item[Item::getPk()]);
            $avs = explode(',', $item['is_in_stock']);

            //left first item only
            unset($ids[(int) array_search(1, $avs)]);

            $copies += array_merge($copies, $ids);
        }

        //delete all copies
        (new WalkChunk(1000))
            ->setFnGet(function ($page, $size) use ($copies) {
                return array_slice($copies, ($page - 1) * $size, $size);
            })
            ->setFnDo(function ($items) use ($mysql) {
                $mysql->deleteMany(Item::getTable(), new MysqlQuery(['params' => [], 'where' => [Item::getPk() => $items]]));
            })
            ->run();

        return count($copies);
    }
}