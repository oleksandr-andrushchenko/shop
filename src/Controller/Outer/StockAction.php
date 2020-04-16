<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Controller\Outer\ProcessTypicalPageTrait;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_SHOP\Manager\Stock;
use SNOWGIRL_CORE\View\Widget\Ad\LongHorizontal as LongHorizontalAd;

class StockAction
{
    use PrepareServicesTrait;
    use ProcessTypicalPageTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $view = $this->processTypicalPage($app, 'stock', [
            'month' => $month = ['Января', 'Февраля', 'Марта', 'Апреля', 'Мая', 'Июня', 'Июля', 'Августа', 'Сентября', 'Октября', 'Ноября', 'Декабря'][date('m') - 1],
            'period' => $period = $month . ' ' . date('Y'),
            'meta_title' => 'Обзор супер скидок {period}',
            'meta_description' => 'Сумасшедшие скидки {period}',
            'meta_keywords' => 'Скидки,распродажа,{month}',
            'h1' => 'Скидки {period} на женский каталог, распродажа',
            'description' => 'Дорогие красавицы, мы собрали для Вас самые выгодные предложения с интернета.
            Надеемся, что представленные скидки,  распродажи и акции порадуют Вас, и Вы сможете выбрать и купить нужную Вам вещь по доступной цене.
            Сезон скидок открыт и приятного шопинга!'
        ]);

        $content = $view->getContent()->addParams([
            'items' => $app->managers->stock->clear()
                ->setWhere(['is_active' => 1])
                ->setOrders(['stock_id' => SORT_DESC])
                ->setLimit(7)
                ->cacheOutput(Stock::CACHE_STOCK_PAGE)
                ->getObjects(),
            'manager' => $app->managers->stock,
        ]);

        if ((!$app->request->getDevice()->isMobile()) && $banner = $app->ads->findBanner(LongHorizontalAd::class, 'stock', [], $view)) {
            $content->banner = $banner;
        }

        $app->response->setHTML(200, $view);
    }
}