<?php

namespace SNOWGIRL_SHOP\View\Widget\Grid;

use SNOWGIRL_CORE\View\Node;
use SNOWGIRL_CORE\View\Widget;
use SNOWGIRL_CORE\View\Widget\Ad;

class Items extends Widget
{
    protected $items = [];
    protected $class;
    protected $itemTemplate = 'catalog';
    protected $offset = 0;
    protected $buyOnClick;
    protected $currency;
    protected $propName;
    protected $propUrl;
    protected $propTotal;
    protected $h21;
    /** @var Ad */
    protected $banner;
    protected $bannerIndex;

    protected function makeParams(array $params = []): array
    {
        return array_merge([
            'buyOnClick' => $this->app->config('catalog.force_buy_on_click', false),
            'currency' => (object)[
                'iso' => $tmp = $this->app->config('catalog.currency', 'UAH'),
                'text' => 'UAH' == $tmp ? 'грн' : 'руб'
            ]
        ], parent::makeParams($params));
    }

    protected function addScripts(): Widget
    {
        return parent::addScripts()
            ->addCssScript('@shop/widget/grid.items.css');
    }

    public static function getBannerCellsCount()
    {
        return 2;
    }

    protected function stringifyPrepare()
    {
        if (is_object($this->banner)) {
            $s = count($this->items);
            $this->bannerIndex = intdiv($s, 2) - $this->getBannerCellsCount();
        } else {
            $this->bannerIndex = -1;
        }

        return parent::stringifyPrepare();
    }

    protected function echoItem($k)
    {
        if ($this->bannerIndex == $k) {
            $this->banner->addDomClass('centered');
            echo $this->makeNode('div', ['class' => 'col-xs-6 col-mb-4 col-sm-6 centered-wrapper widget-banner-wrapper'])
                ->append($this->banner);
        }

        echo $this->app->views->entity($this->items[$k], $this->itemTemplate, [
            'currency' => $this->currency,
            'position' => $this->offset + $k + 1,
            'buyOnClick' => $this->buyOnClick
        ]);
    }

    protected function getNode(): ?Node
    {
        return null;
    }

    public function isOk(): bool
    {
        return 0 < count($this->items);
    }
}