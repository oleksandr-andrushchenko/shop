<?php

namespace SNOWGIRL_SHOP\Controller\Console;

use SNOWGIRL_CORE\Controller\Console\PrepareServicesTrait;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_SHOP\App\Console as App;

class FixImportSourceAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$id = $app->request->get('param_1')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        if (!$source = $app->managers->sources->find($id)) {
            throw (new NotFound)->setNonExisting('source');
        }

        $import = $app->managers->sources->getImport($source);

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

        $aff = $app->managers->sources->updateOne($source);

        $app->response->setBody($aff ? 'DONE' : 'FAILED');
    }
}