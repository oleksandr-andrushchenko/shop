<?php

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Http\Exception\BadRequestHttpException;
use SNOWGIRL_CORE\Http\Exception\NotFoundHttpException;
use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_SHOP\Http\HttpApp as App;
use SNOWGIRL_SHOP\Entity\PartnerLinkHolderInterface;
use SNOWGIRL_SHOP\Manager\GoLinkBuilderInterface;
use Throwable;

class GoAction
{
    use PrepareServicesTrait;

    public function __invoke(App $app, $type = null, $id = null, $source = null)
    {
        $this->prepareServices($app);

        if (!$type = $type ?: $app->request->get('type')) {
            throw (new BadRequestHttpException)->setInvalidParam('type');
        }

        if (!$id = $id ?: $app->request->get('id')) {
            throw (new BadRequestHttpException)->setInvalidParam('id');
        }

        if ('item' == $type) {
            if (!$holder = $app->managers->items->find($id)) {
                if ($archive = $app->managers->archiveItems->find($id)) {
                    $app->views->getLayout()
                        ->addMessage(implode(' ', [
                            'Товар снят с продажи, извините за неудобства.',
                            'Пожалуйста, обратите внимание на похожие модели',
                        ]), Layout::MESSAGE_WARNING);

                    $app->request->redirect($app->managers->items->getLink($archive), 301);
                } elseif ($idTo = $app->managers->itemRedirects->getByIdFrom($id)) {
                    if ($itemTo = $app->managers->items->find($idTo)) {
                        $app->request->redirect($app->managers->items->getLink($itemTo), 301);
                    }

                    throw (new NotFoundHttpException)->setNonExisting('item');
                } else {
                    throw (new NotFoundHttpException)->setNonExisting('item');
                }
            }
        } elseif ('shop' == $type) {
            if (!$holder = $app->managers->vendors->find($id)) {
                throw (new NotFoundHttpException)->setNonExisting('vendor');
            }

            if (!$holder->isActive()) {
                $app->views->getLayout()
                    ->addMessage(implode(' ', [
                        'Магазин более не доступен, извините за неудобства.',
                        'Пожалуйста, обратите внимание на другие магазины',
                    ]), Layout::MESSAGE_WARNING);

                $app->request->redirect($app->router->makeLink('default', ['action' => 'shops']), 301);
            }
        } elseif ('stock' == $type) {
            if (!$holder = $app->managers->stock->find($id)) {
                throw (new NotFoundHttpException)->setNonExisting('stock');
            }

            if (!$holder->isActive()) {
                $app->views->getLayout()
                    ->addMessage(implode(' ', [
                        'Акция более не доступна, извините за неудобства.',
                        'Пожалуйста, обратите внимание на другие акционные предложения',
                    ]), Layout::MESSAGE_WARNING);

                $app->request->redirect($app->router->makeLink('default', ['action' => 'stock']), 301);
            }
        } else {
            throw (new NotFoundHttpException)->setNonExisting('type');
        }

        if ($app->configMaster) {
            /** @var GoLinkBuilderInterface $manager */
            $manager = $app->managers->getByEntity($holder);
            $link = $app->configMaster('domains.master') . $manager->getGoLink($holder, 'slave');
            $app->request->redirect($link, 302);
        }

        if (!$app->request->has('source')) {
            try {
                $app->analytics->logGoHit($holder);
            } catch (Throwable $e) {
                $app->container->logger->error($e);
            }
        }

        /**
         * @var PartnerLinkHolderInterface $holder
         */

        if (!$link = $holder->getPartnerLink()) {
            if (('shop' == $type) && ($fallbackVendor = $app->config('catalog.fallback_vendor'))) {
                if ($holder->getId() != $fallbackVendor) {
                    $vendor = $app->managers->vendors->find($fallbackVendor);

                    if ($vendor) {
                        $link = $vendor->getPartnerLink();
                    }
                }
            }

            if (!$link) {
                $app->container->logger->error('invalid partner link', compact('type', 'id'));
                $app->views->getLayout()->addMessage('Предложение не доступно, извините за неудобства.',
                    Layout::MESSAGE_WARNING);
                $app->request->redirect($app->request->getReferer(), 301);
            }
        }

        $tmp = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>{$app->getSite()}</title>
<meta http-equiv="refresh" content="0; URL={$link}">
<script type="text/javascript">window.location.href = '{$link}'</script>
</head>
<body></body>
</html>
HTML;

        $app->response
//            ->setCode(200)
//            ->setRawHeader('200 OK')
            ->setBody($tmp)//            ->send(true)
        ;
    }
}