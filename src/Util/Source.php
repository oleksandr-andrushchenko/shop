<?php

namespace SNOWGIRL_SHOP\Util;

use SNOWGIRL_CORE\Util;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;
use SNOWGIRL_SHOP\Http\HttpApp;

/**
 * Class Source
 *
 * @property HttpApp|ConsoleApp app
 * @package SNOWGIRL_SHOP\Util
 */
class Source extends Util
{
    public function doFixSource(ImportSource $source): ?bool
    {
        $import = $this->app->managers->sources->getImport($source);

        $columns = $import->getMeta()['columns'];

        $filters = $source->getFileFilter(true);

        foreach ($filters as $column => $options) {
            if (!in_array($column, $columns)) {
                unset($filters[$column]);
            }
        }

        $source->setFileFilter($filters);

        $mappings = $source->getFileMapping(true);

        foreach ($mappings as $column => $options) {
            if (!isset($options['column'])) {
                unset($mappings[$column]);
            }

            if (!in_array($options['column'], $columns)) {
                unset($mappings[$column]);
            }
        }

        $source->setFileMapping($mappings);

        $aff = $this->app->managers->sources->updateOne($source);

        return $aff;
    }
}