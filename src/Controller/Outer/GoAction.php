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

        $holder = null;

        if ('item' == $type) {
            if ($holder = $app->managers->items->find($id)) {
                if ($vendor = $app->managers->items->getVendor($holder)) {
                    if ($vendor->isFake()) {
                        if ($targetVendor = $app->managers->vendors->find($vendor->getTargetVendorId())) {
                            $app->request->redirect($app->managers->vendors->getGoLink($targetVendor), 301);
                        } else {
                            $holder = null;
                        }
                    }
                } else {
                    $holder = null;
                }
            } elseif ($idTo = $app->managers->itemRedirects->getByIdFrom($id)) {
                if ($itemTo = $app->managers->items->find($idTo)) {
                    $app->request->redirect($app->managers->items->getGoLink($itemTo), 301);
                }
            }
        } elseif ('shop' == $type) {
            if ($holder = $app->managers->vendors->find($id)) {
                if ($holder->isFake()) {
                    if ($targetVendor = $app->managers->vendors->find($holder->getTargetVendorId())) {
                        $app->request->redirect($app->managers->vendors->getGoLink($targetVendor), 301);
                    } else {
                        $holder = null;
                    }
                }
            }
        } elseif ('stock' == $type) {
            if ($holder = $app->managers->stock->find($id)) {
                if (!$holder->isActive()) {
                    $holder = null;
                }
            }
        }

        if (!$holder) {
            if ($fallbackVendor = $app->managers->vendors->findFallback()) {
                $holder = $fallbackVendor;
            } else {
                throw new NotFoundHttpException('fallback vendor has not been found');
            }
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
            if ($fallbackVendor = $app->managers->vendors->findFallback()) {
                $link = $fallbackVendor->getPartnerLink();
            } else {
                throw new NotFoundHttpException('fallback vendor has not been found');
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