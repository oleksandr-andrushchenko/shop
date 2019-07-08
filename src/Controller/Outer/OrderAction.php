<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Controller\Outer\ProcessTypicalPageTrait;
use SNOWGIRL_CORE\Exception\HTTP\MethodNotAllowed;
use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\Size;

class OrderAction
{
    use PrepareServicesTrait;
    use ProcessTypicalPageTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        if (!$app->request->isGet()) {
            throw (new MethodNotAllowed)->setValidMethod('get');
        }

        $view = $this->processTypicalPage($app, 'order', [
            'meta_title' => 'Корзина интернет-магазина {site}',
            'meta_description' => 'Корзина товаров в интернет магазине {site}. Оформление заказа',
            'meta_keywords' => 'Корзина, заказ, оплата, покупка, оформление',
            'h1' => 'Корзина',
            'description' => 'Офорление заказа'
        ]);

        $form = $app->views->orderForm([], $view);

        if ($app->request->isPost()) {
            $isOk = $form->process($app->request, $msg);
            $view->addMessage($msg, $isOk ? Layout::MESSAGE_SUCCESS : Layout::MESSAGE_ERROR);
            $app->request->redirectToRoute('catalog');
        }

        $cart = $app->request->getSession()->get('cart', []);

        $itemToParams = [];

        array_walk($cart, function ($item) use (&$itemToParams) {
            $itemToParams[$item['item_id']] = [$item['size_id'], $item['quantity']];
        });

        $cart = array_keys($itemToParams);
        $visited = $app->request->getSession()->get('visited', []);

        $items = $app->managers->items->findMany(array_merge($cart, $visited));

        $sizePk = Size::getPk();

        $view->getContent()->addParams([
            'cart' => array_map(function ($id) use ($app, $items, $itemToParams, $sizePk) {
                /** @var Item[] $items */
                return $items[$id]->setRawVar('size', $itemToParams[$id][0])
                    ->setRawVar('quantity', $itemToParams[$id][1])
                    ->setRawVar('sizes', $app->managers->items->getAttrs($items[$id], [$sizePk])[$sizePk]);
            }, $cart),
            'visited' => array_map(function ($id) use ($items) {
                /** @var Item[] $items */
                return $items[$id];
            }, $visited),
            'form' => $form->stringify()
        ]);

        $view->setNoIndexNoFollow();

        $app->response->setNoIndexNoFollow()
            ->setHTML(200, $view);
    }
}