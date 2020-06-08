<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Helper;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;

class GetAttrSuggestionsAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $table = $app->request->get('name');
        $prefix = !empty($app->request->get('prefix'));
        $query = $app->request->get('q');

        $sizeMax = 300;
        $size = $app->request->get('per_page', $sizeMax);
        $size = min($sizeMax, $size);

        $page = $app->request->get('page');

        $uri = new URI($app->request->getParams());

        /** @var ItemAttrManager $manager */
        $manager = $app->managers->getByTable($table)
            ->clear()
            ->setOffset(($page - 1) * $size)
            ->setLimit($size);

        $pk = $manager->getEntity()->getPk();

        $output = [];

        $uriParams = $uri->getParamsArray();

        foreach ($manager->getObjectsByUriAndQuery($uri, $query, true, $prefix) as $item) {
            $tmp2 = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'uri' => (new URI($uriParams))
                    ->set(URI::PAGE_NUM, null)
                    ->inverse($pk, $item->getId(), $isWas)
                    ->output(URI::OUTPUT_DEFINED, false, $isNoFollow),
                'isWas' => $isWas,
                'isNoFollow' => $isNoFollow,
                'count' => ($tmp = $item->getRawVar('items_count')) ? Helper::makeNiceNumber($tmp) : 0
            ];

            if ('color_id' == $pk) {
                $tmp2['hex'] = $item->get('hex');
            }

            $output[] = $tmp2;
        }

        $app->response->setJSON(200, $output);
    }
}