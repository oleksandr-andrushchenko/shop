<?php

namespace SNOWGIRL_SHOP\View\Widget\Grid;

use SNOWGIRL_CORE\Exception;
use SNOWGIRL_CORE\Helper;
use SNOWGIRL_CORE\View\Node;
use SNOWGIRL_CORE\View\Widget;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Category;

class Categories extends Widget
{
    protected $items = [];
    protected $padding;
    /** @var URI */
    protected $uri;
    protected $tmpMap;

    protected function makeParams(array $params = []): array
    {
        if (isset($params['uri']) && !$params['uri'] instanceof URI) {
            throw new Exception('invalid "uri" param');
        }

        return $params;
    }

    protected function getNode(): ?Node
    {
        return $this->makeNode('div', [
            'class' => implode(' ', ['row', $this->getDomClass(), $this->padding ? ('p' . $this->padding) : ''])
        ]);
    }

    protected function addScripts(): Widget
    {
        return $this->addCssScript('@shop/widget/grid.categories.css');
    }

    protected function getLink($category)
    {
        /** @var Category $category */
        if ($this->uri) {
            return $this->uri->copy()
                ->set('category_id', $category->getId())
                ->output(URI::OUTPUT_DEFINED_SAFE);
        }

        return $this->app->managers->categories->getLink($category);
    }

    protected function getCount($category)
    {
        /** @var Category $category */
        return Helper::makeNiceNumber($this->app->managers->categories->getItemsCount($category));
    }

    protected function stringifyPrepare()
    {
        switch ($s = count($this->items)) {
            case 12:
                return $this->tmpMap = [4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4];
//                return $this->tmpMap = [6, 6, 4, 4, 4, 6, 6, 4, 4, 4, 6, 6];
            case 6:
                return $this->tmpMap = [4, 4, 4, 4, 4, 4];
            case 5:
                return $this->tmpMap = [6, 6, 4, 4, 4];
            case 4:
                return $this->tmpMap = [6, 6, 6, 6];
            case 3:
                return $this->tmpMap = [12, 6, 6];
            case 2:
                return $this->tmpMap = [6, 6];
            case 1:
                return $this->tmpMap = [12];
            case 0:
            default:
                for ($i = 0; $i < $s; $i++) {
                    if (0 == intdiv($i, 3) % 2) {
                        $this->tmpMap[$i] = 0 == $i % 3 ? 6 : 3;
                    } else {
                        $this->tmpMap[$i] = 2 == $i % 3 ? 6 : 3;
                    }
                }
                break;
        }

        return parent::stringifyPrepare();
    }
}