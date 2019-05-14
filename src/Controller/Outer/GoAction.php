<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/15/19
 * Time: 12:18 AM
 */

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_CORE\Service\Logger;
use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_SHOP\Entity\PartnerLinkHolderInterface;
use SNOWGIRL_SHOP\Manager\GoLinkBuilderInterface;

class GoAction
{
    use PrepareServicesTrait;

    /**
     * @param App  $app
     * @param null $type
     * @param null $id
     * @param null $source
     *
     * @throws NotFound
     */
    public function __invoke(App $app, $type = null, $id = null, $source = null)
    {
        $this->prepareServices($app);

        if (!$type = $type ?: $app->request->get('type')) {
            throw (new BadRequest)->setInvalidParam('type');
        }

        if (!$id = $id ?: $app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        if ('item' == $type) {
            if (!$holder = $app->managers->items->find($id)) {
                if ($archive = $app->managers->archiveItems->find($id)) {
                    $app->views->getLayout()
                        ->addMessage(implode(' ', [
                            'Товар снят с продажи, извините за неудобства.',
                            'Пожалуйста, обратите внимание на похожие модели'
                        ]), Layout::MESSAGE_WARNING);

                    $app->request->redirect($app->managers->items->getLink($archive), 301);
                } elseif ($idTo = $app->managers->itemRedirects->getByIdFrom($id)) {
                    if ($itemTo = $app->managers->items->find($idTo)) {
                        $app->request->redirect($app->managers->items->getLink($itemTo), 301);
                    }

                    throw new NotFound;
                } else {
                    throw new NotFound;
                }
            }
        } elseif ('shop' == $type) {
            if (!$holder = $app->managers->vendors->find($id)) {
                throw new NotFound;
            }

            if (!$holder->isActive()) {
                $app->views->getLayout()
                    ->addMessage(implode(' ', [
                        'Магазин более не доступен, извините за неудобства.',
                        'Пожалуйста, обратите внимание на другие магазины'
                    ]), Layout::MESSAGE_WARNING);

                $app->request->redirect($app->router->makeLink('default', ['action' => 'shops']), 301);
            }
        } elseif ('stock' == $type) {
            if (!$holder = $app->managers->stock->find($id)) {
                throw new NotFound;
            }

            if (!$holder->isActive()) {
                $app->views->getLayout()
                    ->addMessage(implode(' ', [
                        'Акция более не доступна, извините за неудобства.',
                        'Пожалуйста, обратите внимание на другие акционные предложения'
                    ]), Layout::MESSAGE_WARNING);

                $app->request->redirect($app->router->makeLink('default', ['action' => 'stock']), 301);
            }
        } else {
            throw (new NotFound)->setNonExisting('type');
        }

        if ($app->configMaster) {
            /** @var GoLinkBuilderInterface $manager */
            $manager = $app->managers->getByEntity($holder);
            $link = $app->configMaster->domains->master . $manager->getGoLink($holder, 'slave');
            $app->request->redirect($link, 302);
        }

        if (!$app->request->has('source')) {
            try {
                $app->analytics->logGoHit($holder);
            } catch (\Exception $ex) {
                $app->services->logger->makeException($ex);
            }
        }

        /** @var PartnerLinkHolderInterface $holder */

        if (!$link = $holder->getPartnerLink()) {
            $app->services->logger->make('invalid partner link(type=' . $type . ', id=' . $id . ')',
                Logger::TYPE_ERROR);
            $app->views->getLayout()->addMessage('Предложение не доступно, извините за неудобства.',
                Layout::MESSAGE_WARNING);
            $app->request->redirect($app->request->getReferer(), 301);
        }

        if ($app->isDev()) {
            die('Redirecting to "' . $link . '"...');
        }

        $tmp = <<<HTML
<!DOCTYPE html>
<html>
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
//            ->setHttpResponseCode(200)
//            ->setRawHeader('200 OK')
            ->setBody($tmp)//            ->send(true)
        ;
    }
}