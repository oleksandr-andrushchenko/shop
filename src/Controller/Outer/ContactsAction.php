<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Controller\Outer\ProcessTypicalPageTrait;
use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_SHOP\Http\HttpApp as App;

class ContactsAction
{
    use PrepareServicesTrait;
    use ProcessTypicalPageTrait;

    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $view = $this->processTypicalPage($app, 'contacts', [
            'meta_title' => 'Контакты',
            'meta_description' => 'Как нас найти',
            'meta_keywords' => 'Контакты, телефоны, связь',
            'h1' => 'Контакты',
            'description' => 'Наши контакты. Как добраться'
        ]);

        $content = $view->getContent();

        $content->addParams([
            'contacts' => [
                'email' => $app->config('site.email'),
                'phone' => $app->config('site.phone'),
                'time' => $app->config('site.time'),
                'address' => $app->config('site.address')
            ],
            'map' => $app->views->googleMap(['center' => [
                'latitude' => $app->config('site.latitude'),
                'longitude' => $app->config('site.longitude')
            ]], $view)->stringify()
        ]);

        $form = $app->views->contactForm([], $view);

        if ($itemId = $app->request->get('item_id')) {
            if ($item = $app->managers->items->find($itemId)) {
                if ($sizeId = $app->request->get('size_id')) {
                    $size = $app->managers->sizes->find($sizeId);
                } else {
                    $size = '';
                }

                $href = $app->managers->items->getLink($item, [], 'master');
                $size = $size->getName();
                $qnt = $app->request->get('quantity');

                $body = T('contacts.buy_item', $href, $size, $qnt);
                $form->setParam('body', $body);
            }
        }

        if ($app->request->isPost()) {
            $isOk = $form->process($app->request, $msg);
            $view->addMessage($msg, $isOk ? Layout::MESSAGE_SUCCESS : Layout::MESSAGE_ERROR);
        }

        $content->addParams([
            'form' => $form->stringify()
        ]);

        $app->response->setHTML(200, $view);
    }
}