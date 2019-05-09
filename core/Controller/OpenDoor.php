<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 3/7/16
 * Time: 6:47 AM
 */

namespace SNOWGIRL_SHOP\Controller;

use SNOWGIRL_CORE\Ads;
use SNOWGIRL_CORE\Response;
use SNOWGIRL_CORE\Service\Logger;
use SNOWGIRL_SHOP\App;
use SNOWGIRL_SHOP\Entity\Brand;
use SNOWGIRL_SHOP\Entity\Category;
use SNOWGIRL_SHOP\Catalog\URI\Manager as CatalogUriManager;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Import\Source;
use SNOWGIRL_SHOP\Entity\Item;
use SNOWGIRL_SHOP\Entity\PartnerLinkHolderInterface;
use SNOWGIRL_SHOP\Entity\Size;
use SNOWGIRL_SHOP\Item\URI\Manager as ItemUriManager;
use SNOWGIRL_SHOP\Manager\GoLinkBuilderInterface;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttrManager;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_SHOP\View\Widget\Grid\Items as ItemsGrid;
use SNOWGIRL_SHOP\Catalog\SEO;
use SNOWGIRL_SHOP\Catalog\SRC;
use SNOWGIRL_CORE\View\Layout;
use SNOWGIRL_CORE\Helper;
use SNOWGIRL_CORE\Exception\HTTP\BadRequest;
use SNOWGIRL_CORE\Exception\HTTP\NotFound;
use SNOWGIRL_CORE\Exception\HTTP\MethodNotAllowed;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_SHOP\Manager\Stock;
use SNOWGIRL_CORE\View\Widget\Ad\LongHorizontal as LongHorizontalAd;
use SNOWGIRL_CORE\View\Widget\Ad\LargeRectangle as LargeRectangleAd;

/**
 * Class OpenDoor
 *
 * @property App app
 * @package SNOWGIRL_SHOP\Controller
 */
class OpenDoor extends \SNOWGIRL_CORE\Controller\OpenDoor
{
    /**
     * @return bool|Response
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function actionDefault()
    {
        //@todo move this func to web-server
//        if ($this->checkFile()) {
//            return true;
//        }
//
//        if ($this->checkRedirect()) {
//            return true;
//        }

//        if ($this->checkCustomPage()) {
//            return true;
//        }

//        return parent::actionDefault();

        return $this->actionCatalog();
    }

    /**
     * @return Response|bool
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function actionIndex()
    {
        $categories = $this->app->managers->categories;

        //cache all categories...
        $categories->findAll();

        $view = $this->processTypicalPage('index', [
            'site' => $site = $this->app->getSite(),
            'meta_title' => 'Лучший женский каталог',
            'meta_description' => 'Лучший женский каталог',
            'meta_keywords' => 'Каталог,женский,огромный,выбор',
            'h1' => 'Женский каталог',
            'description' => 'Женский каталог, огромный выбор',
//            'image' => $image = isset($data['topBanners'][0]) ? $data['topBanners'][0]->getImage() : null
        ]);

        $this->addVerifications($view);

        $content = $view->getContent();

        $view->about = (string)$this->app->views->get('@snowgirl-shop/index/about.phtml', [
            'h1' => $content->title,
            'content' => $content->description,
            'site' => $site,
            'uri' => $this->app->router->makeLink('default'),
//            'image' => $image
        ]);

//        $nonEmptyImage = new Expr('`image` IS NOT NULL');
        $ratingDesc = ['rating' => SORT_DESC];

        $content->addParams([
            'carousel' => $this->app->views->carousel(['items' => $this->app->managers->banners->clear()
                ->setWhere([
                    'type' => 'index',
                    'is_active' => 1,
//                    $nonEmptyImage
                ])
                ->setOrders(['banner_id' => SORT_DESC])
                ->cacheOutput(true)
                ->getObjects()], $view),
            'repeatTimes' => $repeatTimes = 4,
            'categoriesPerBlock' => $categoriesPerBlock = 6,
            //@todo available only
            //@todo use categories::findAll cache
            'categories' => $categories->clear()
//                ->setWhere($nonEmptyImage)
                ->setOrders($ratingDesc)
                ->setLimit($categoriesPerBlock * $repeatTimes)
                ->cacheOutput(true)
                ->getObjects(),
            'brandsPerBlock' => $brandsPerBlock = 24,
            //@todo available only
            'brands' => $this->app->managers->brands->clear()
//                ->setWhere($nonEmptyImage)
                ->setOrders($ratingDesc)
                ->setLimit($brandsPerBlock * $repeatTimes)
                ->cacheOutput(true)
                ->getObjects(),
            'itemsPerBlock' => $itemsPerBlock = 12,
            'currency' => $this->getCurrencyObject(),
            'items' => $this->app->managers->items->clear()
//            ->setStorage(Manager::STORAGE_FTDBMS)
                ->setWhere(['is_in_stock' => 1])
                ->setOrders($ratingDesc)
                ->setLimit($itemsPerBlock * $repeatTimes)
                ->cacheOutput(true)
                ->getObjects(),
            'menu' => array_values($this->app->managers->pagesRegular->getMenu()),
            'vkontakteLike' => $this->app->views->vkontakteLike($view)->stringify(),
            'facebookLike' => $this->app->views->facebookLike($view)->stringify()
        ]);

        $this->app->managers->items->addLinkedObjects($content->items, Brand::class);

        if ($this->app->request->getDevice()->isMobile()) {
            $catalogNav = [];

            $categoriesList = $categories->getRootObjects();
            $categories->sortByRating($categoriesList);

            foreach (array_slice($categoriesList, 0, 3) as $category) {
                $catalogNav[$category->getName()] = $this->app->managers->categories->getLink($category);
            }

            $typesNames = SEO::getTypesToTexts(true);

            foreach (URI::TYPE_PARAMS as $type) {
                $catalogNav[$typesNames[$type]] = (new URI([$type => 1]))->output();
            }

            $headerNav = $view->getParam('headerNav');

            $headerNav = array_filter($headerNav, function ($item) {
                return $item != '/contacts';
            });

            $content->addParams([
                'mobileHeaderNav' => $headerNav,
                'mobileCatalogNav' => $catalogNav
            ]);
        } else {
            if ($banner = $this->app->ads->findBanner(LongHorizontalAd::class, 'index', [], $view)) {
                $content->banner = $banner;
            }

            $content->advantages = [
                ['Все популярные предложения в одном месте', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACkAAAApCAYAAACoYAD2AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA+tpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMDY3IDc5LjE1Nzc0NywgMjAxNS8wMy8zMC0yMzo0MDo0MiAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczpkYz0iaHR0cDovL3B1cmwub3JnL2RjL2VsZW1lbnRzLzEuMS8iIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIiB4bWxuczpzdFJlZj0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL3NUeXBlL1Jlc291cmNlUmVmIyIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ0MgMjAxNSAoV2luZG93cykiIHhtcDpDcmVhdGVEYXRlPSIyMDE2LTExLTA3VDExOjAyOjE0KzAyOjAwIiB4bXA6TW9kaWZ5RGF0ZT0iMjAxNi0xMS0wN1QxMTowMjoxOSswMjowMCIgeG1wOk1ldGFkYXRhRGF0ZT0iMjAxNi0xMS0wN1QxMTowMjoxOSswMjowMCIgZGM6Zm9ybWF0PSJpbWFnZS9wbmciIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6RTMyN0E4MEVBNEM4MTFFNkJDMDdDOEZDQTNBQTIxMjgiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6RTMyN0E4MEZBNEM4MTFFNkJDMDdDOEZDQTNBQTIxMjgiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDpFMzI3QTgwQ0E0QzgxMUU2QkMwN0M4RkNBM0FBMjEyOCIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDpFMzI3QTgwREE0QzgxMUU2QkMwN0M4RkNBM0FBMjEyOCIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PqodeMYAAAKmSURBVHja7JhNSFVBFMff9fXhS0kzgigDcZEEgUTRKhCiRRAtaldpltmXim00WkSbCFFB+qAvNFpV0MKtKyEoUQufH7UoBKMgCFpkgT014/U/8L8w7zbJzPO+3pge+PEuZ+69879nzsyceV48Ho8Y2ipwBZwEmyLp22dQCXpNH1hh8fIOUA+mwVf6CsEPkMffNWASJNm+FswQsVywEdwBZZkQeYS/18EQKOL1GfAINLHzfWAnGAFd4C5I8FkR2AK2gpjin9dyLERGlY6kg4/gFzs6Cz6xfTe4Dw5pnt8cSJ/QI+lbCXPyDUXGKKqO7RLBS2AAHFCiX5BuEnsWE0cit4U5mbDMSfkQT3mXvCOfHxnqcLcryb+OeBQYoUBfuN8epd8LvOu2qUDb4b7F+y8yL03tG0fhA+kHjzM13Drzh9WzbLOynMgiMJ3IKkbBBDVqNm1/47iJyDIuyNky6XvbfCJXgydcGrJlslI8VVaKP0S2gh0OpOB2cEMn8iBodGiu1IJjqshi8DCMpSJku+dXSiLyJljv4Mojc6PTF1lhuGgnNL5ZQ99PA5/O9sguJyJfBxreg2cB3zB4ofH1GfqeG/hearS8BXMi8hwYo/Md60BJ3FHlxhPgPIgrPjlGnNb4ag3vU31S9p0Ch5V+J7ixpOzdskZNBb6kkKWXagUsGsLy6fpN8alVkHpjN2dWMWvCTNsXRng/69Ipk1Jt70Iq6TRsA1mZTj3pGc78hZZkyf+yVFuUImUYL4NryrH2n5rJGUfqu6u87tEs6k6IHGedGeNO4WQkZY89ujxxlkU6kpPJsHaNJRvJkiydeb7biJx0ceJUg0GeY5JZRjS8AjX+aEokm0GbQ4GT/z93gQegVOoGiWSDw3PmgkRTIlnu4B8DKfZbgAEATEjSd2zJTjoAAAAASUVORK5CYII='],
                ['Удобный каталог с фильтрами', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACgAAAAoCAYAAACM/rhtAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA+tpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMDY3IDc5LjE1Nzc0NywgMjAxNS8wMy8zMC0yMzo0MDo0MiAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczpkYz0iaHR0cDovL3B1cmwub3JnL2RjL2VsZW1lbnRzLzEuMS8iIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIiB4bWxuczpzdFJlZj0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL3NUeXBlL1Jlc291cmNlUmVmIyIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ0MgMjAxNSAoV2luZG93cykiIHhtcDpDcmVhdGVEYXRlPSIyMDE2LTExLTA3VDExOjE0OjA5KzAyOjAwIiB4bXA6TW9kaWZ5RGF0ZT0iMjAxNi0xMS0wN1QxMToxNDoxMyswMjowMCIgeG1wOk1ldGFkYXRhRGF0ZT0iMjAxNi0xMS0wN1QxMToxNDoxMyswMjowMCIgZGM6Zm9ybWF0PSJpbWFnZS9wbmciIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6OENDNEVEQzRBNENBMTFFNjk0MzREM0VGRTczOTMxNjAiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6OENDNEVEQzVBNENBMTFFNjk0MzREM0VGRTczOTMxNjAiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDo4Q0M0RURDMkE0Q0ExMUU2OTQzNEQzRUZFNzM5MzE2MCIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo4Q0M0RURDM0E0Q0ExMUU2OTQzNEQzRUZFNzM5MzE2MCIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/Pqq3oFQAAAInSURBVHja7JhLKERRGMfPva63UkrISlEodpKVd94rIQtJrISVlRVJKWVFkRV5ZGUzSikLj7AZ8goJiZUFFogw/odPnU53zMw17r00X/3q3jPT6dd35jvnzKc4nU6G0EAHaAbJIISZF0/gCIyCEfAmfqgRDlDCrIlQkAmGQQGoFSVVypxVcnJU0yoyUbCF2SuaZMFkmwmmyILBNhMMlotEDMVCMZfeoMpsHgHBgKDVoflYXYqnqvMylMASW7XEQeDVy+VQrMhgVGCJ/3AVH4JL+Sem2ShZaf92m1EE3I37yneX1Vi7ZnAAHIMzUGo3wUHQSc+RYO5LUvWyil06VesyQLfO/OEgWxoLI8k8MzPYIwnyUysJPIJysK4j2adaKDcJNkA6uANlYFPeGzUvq9ifZzGXmwJ19L5EHYUDaiAsgiywzJsKZheJLMcjjiTTKJPF1Abhy36vWiwnS6aSZBuXM3Ob4XLTbuS+Ih7Myk6aSXIzoMbD965BA5Pab9z25Rfvg77IFYIdvbP4THjvAtF+FOz3QW7X3WVhXHhvBbcGTwnOqTR/Pc3fCIZ0TqNr2mJ2v7vN8EN6zU8ZWxCeM0AiCU6AdnCiI7fn6br1DIpAL7j6oeC88FxBW8aqMLYlyOV7kvs4DaiJLkYE++wbG4k7oQpXKIO5dBmoBFV0pedy+0b/kzwQP4kYkENVfEFj2+yziz8Gzv35p8lIJIAbOvwdxKWRid4FGAAW1Yl9wL/AZgAAAABJRU5ErkJggg=='],
                ['Только проверенные магазины', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAC0AAAAjCAYAAAAAEIPqAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA+tpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMDY3IDc5LjE1Nzc0NywgMjAxNS8wMy8zMC0yMzo0MDo0MiAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczpkYz0iaHR0cDovL3B1cmwub3JnL2RjL2VsZW1lbnRzLzEuMS8iIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIiB4bWxuczpzdFJlZj0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL3NUeXBlL1Jlc291cmNlUmVmIyIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ0MgMjAxNSAoV2luZG93cykiIHhtcDpDcmVhdGVEYXRlPSIyMDE2LTExLTA3VDExOjAyOjU5KzAyOjAwIiB4bXA6TW9kaWZ5RGF0ZT0iMjAxNi0xMS0wN1QxMTowMzowMSswMjowMCIgeG1wOk1ldGFkYXRhRGF0ZT0iMjAxNi0xMS0wN1QxMTowMzowMSswMjowMCIgZGM6Zm9ybWF0PSJpbWFnZS9wbmciIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6RkM5OTRBQTRBNEM4MTFFNjkwMDRDN0FEQjlCOTlBMTQiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6RkM5OTRBQTVBNEM4MTFFNjkwMDRDN0FEQjlCOTlBMTQiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDpGQzk5NEFBMkE0QzgxMUU2OTAwNEM3QURCOUI5OUExNCIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDpGQzk5NEFBM0E0QzgxMUU2OTAwNEM3QURCOUI5OUExNCIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/Plc2oPwAAAQvSURBVHjavJgJiE1hFMffw1hnbM2QbZoYO2XJTiRLJLKNJVvIVtZsWTORsSShhhRmNGMfxlgilCVLMSlrGYylQRRZYgye/6n/reNz33Lf3Pf+9evd79737jv3u+c7y+fNz8/3hKiyoB3oAdqDZJAEKoNY8BN8A2/BE/AAXCOfPS6qXAjfaQmmglGgToDvlSc1QHMwmOeLwRmwD5wCf0prdJkgxh4H98C8IAYHUgUwFOSC+2B0JGY6BqwBC3lsSv74Oh/mOfgAfvFaddAANAFdQCdQSf1W3sABMIVv74UbRseDI6CXcf4VSOcfFjq4fxwYBKaDnup8H3AbDAdXSuMe1cAlw2CZxZmgIVjv0GDRFz6o3LMruGlM0HnQO1yj5TMHtFbX5IYtwE71+kujG6AbWAl+K3+X/20cjtELjCfOAgPBe4+7ksixFoxVEyFvODNIUPjPaFk8y9W5q2Cimo1I6DBYpMad6d8hGz2BhnuYICJtsKWt4LIazzeuS1xv5s/oAYZbPPdET5uM2U5Q44rgDhhnZ3QbNT7nia4ugBIee41AUMgSYT9YYhodr8ZFUTa62FjseqY/quM0ME0bXawuVvJEX7Hq+LtRy2htB20towvUhQ5RNlgWWlU1fqaOa9kUZJKVvWUY4ixNCrHyc0t6kYmbPFTjdjbfl1qmrxi9V51MZkUXDUlRNVeNM1TZGsea3U4jxWjpAk6ok+vCqQccqiY4pvxZmoct6noKU7ydulmpc55areI7eazOIqF6DHWt1Dkpg9+oDmlxgN8nWUa/oH9ZMVPi40k+fRUXDZaa464VBVRC26XGU+g6fts+XaSc4U2LVbCfz8UxhuNwu6PBXPBZRl44CCYDn1pTG4Pcr8isrI6y9n2tziWCbJDKcUfQKEhVlkBDt4GXbLW6q+tS4a3iJP1U1V4OPwOWuHbh7Sb7wz1G5XWLJeR4lc0KuRa+ci1IzK1rE2O1pGOZwbpCNyCnjTTuTzn+YvJnIzv+YGiMNxrWpiG6iI9bCRvohj5jYeYGCHFaUszl+jN6CJsAXXFVdOjL3/jWLoJDRrazNIydUUKI95S4XmJndDJdw06/GFF2sOtOYqytRhf5wc2aArpOSYD0ncbJcVJ/59l14+KPZxn8zTZJksFq8Eh16Ncdzn5HzlaKw3Ihm7HcdgshnTOtfUhao93gaRjhLob1Qn8wwl8nEkSbwVLdTZlGZzLR3GW0eBDkhvHMnL+5cMXI2gyTLUm45a6E3VmWSwTarDlGQtUHRoVUbneFm4DslGFncLC9vFBVwCSRzITx2CWjZ4P6dhe8DrZ6nagRd5RacXeqNusZq+P/RDcsYhpP8nMf2fHqZ+4ORMpoJ6rCfZc5foqzXdya87npHqWVJKFlfDupNs21bF6ucNun3dI75oFEFm2yJ3KFrvTP3vhfAQYAIDDf5Qj5DH0AAAAASUVORK5CYII='],
//                    ['Полезная рассылка с выгодными предложениями и текущими акциями + купоны на скидку', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACkAAAAiCAYAAADCp/A1AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA+tpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMDY3IDc5LjE1Nzc0NywgMjAxNS8wMy8zMC0yMzo0MDo0MiAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczpkYz0iaHR0cDovL3B1cmwub3JnL2RjL2VsZW1lbnRzLzEuMS8iIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIiB4bWxuczpzdFJlZj0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL3NUeXBlL1Jlc291cmNlUmVmIyIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ0MgMjAxNSAoV2luZG93cykiIHhtcDpDcmVhdGVEYXRlPSIyMDE2LTExLTA3VDExOjAzOjEyKzAyOjAwIiB4bXA6TW9kaWZ5RGF0ZT0iMjAxNi0xMS0wN1QxMTowMzoxNiswMjowMCIgeG1wOk1ldGFkYXRhRGF0ZT0iMjAxNi0xMS0wN1QxMTowMzoxNiswMjowMCIgZGM6Zm9ybWF0PSJpbWFnZS9wbmciIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6MDUwQzhCRENBNEM5MTFFNjhBMzVBRDBBODBGQUZGMDciIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6MDUwQzhCRERBNEM5MTFFNjhBMzVBRDBBODBGQUZGMDciPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDowNTBDOEJEQUE0QzkxMUU2OEEzNUFEMEE4MEZBRkYwNyIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDowNTBDOEJEQkE0QzkxMUU2OEEzNUFEMEE4MEZBRkYwNyIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PvIv8joAAAOsSURBVHjazJhZSBVRGMev5RKFWVpo0gptLxXVi5ZEG+1BEfTYRguVkUWLUhAtD0ZZQbtkaZv0IAXlLbKFerCNbkKLYBtmtthqmZWV9f/gf+BjuDN3vOi9ffDDc2bOHf9zzvmWMxE+n89DGwzWgVEgDjwEB8EB0OgJo7Xi36ngJpgB4kFrMBDsBUUgOtwiO4OjSkg1uK1mbxrYEE6RkWAB6MD+Efb/gGGgBLQFa8BJkK5mP6Qix7AtM5dJgWKlYCPYynFnQO9wzGQEHKeGS14B+lnuywy/BO3Y/ws+h0ibrGCMmUmz1DV+BoqgYjCT/RegZ4hE7gOLjeMYB4myGVym2hXh8u5atjv5uZ8EVql+Y7hEPmO7u5/ZzGXcNDYkXCJ9arnT1L1ZDPLaxMESAzkjGARGg4TmEnmJS14A3vB6Mtilxj1Rbacw1Avc4T6+DF6B9Q7j24A8/s5RpJd7bw4o5/VDoCPbZ+lpxhIdniWxdKi6JllsM5htI/A0mAeuOgmVB/8EP9Q1+dFEtj+CReCduh9v86xU5nuxKlCo7i20ETiB/S5OK2RNcd3ADtVfDl7zRTwBQlWyahdR2C8lwk5gA+uDEjciI7jMcezLg46zXW8Z58/uMSOZl6tWL1QWQOB5N6Wa2Hwwju33YAnbfcF2NU722CQ/zxLnOqxepL16wU3BCtQie4AcdT1deXo+6G/Zk4U2e1P2bxZ4yv18kWGtPFiBWqSEgVi1n06pLJSq6sxbbMssjfTzPKmgsukEEiPHg0f0+qAEapFVllgXrf5poxKWpMbVu3h+jIPAGGa5SLciM1iSmdRnlv4TOMd2LLeFWCW4FoTA6QzyO5lAKhneVroRWcuK3HjnUj7QxM1i9ZsHTJffgxDoZSbLMLUiS8Uc5aiO3n3B4p15rB0/gCnMNDKTA8D9IAV2YBTxMIaWqt9luQ3mK1jYepgWC9X+rFH3ghFokkWUmpTh4DH7Xe1OpVaRX/mmZtlTwJYmFCwFDgLNXjZZSFLvDdBHVf0NbkR6mJ5yVV+K3skuRW7jkcOfQLEvPMubo0uKupftdrm1sOdqf+ZzOQLZXTCWYcZrM2Y1T6B17It3LwP7myqyzrLsEtRP8MuGG6FOgfo3j84JjLtSfOxxm7utdsVSR47gOby5TLbEW3XOD0qk2FrmYWOZXM6wfLCys28M5iY1ynIfs6THkHxmCWTXwW7WiOaY6+VZpiUtTX9mcfvJo0zFtP9quXXFM9fNJm8J+yfAAJ6Y3+cs9RIaAAAAAElFTkSuQmCC']
            ];
        }

        return $this->app->response->setHTML(200, $view);
    }

    /**
     * @return Response
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function actionBrands()
    {
        $view = $this->processTypicalPage('brands', [
            'meta_title' => 'Бренды',
            'meta_description' => 'Список брендов представленных на нашем сайте',
            'meta_keywords' => 'Бренды,производители,женские',
            'h1' => 'Бренды',
            'description' => 'Популярные и модные женские бренды'
        ]);

        $view->getContent()->addParams([
            'popularItemsGrid' => $this->app->views->brands([
                'uri' => new URI,
                'items' => $this->app->managers->brands->clear()
                    ->setWhere(new Expr('`image` IS NOT NULL'))
                    ->setOrders(['rating' => SORT_DESC])
                    ->setLimit(36)
                    ->getObjects()
            ], $view),
            //@todo create universal search widget (like in header... and implement...)
//            'itemsSearch' => '',
            'groupPerPageSize' => $groupPerPageSize = 21,
            'items' => $items = $this->app->managers->brands->clear()
                ->getNonEmptyGroupedByFirstCharObjects($groupPerPageSize),
            'chars' => array_keys($items)
        ]);

        return $this->app->response->setHTML(200, $view);
    }

    public function actionVendors()
    {
        $this->app->request->redirect($this->app->router->makeLink('default', ['action' => 'shops']), 301);
    }

    /**
     * @return Response
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function actionShops()
    {
        $view = $this->processTypicalPage('vendors', [
            'meta_title' => 'Магазины',
            'meta_description' => 'Магазины, предложения которых представлены в нашем каталоге. Наши партнеры',
            'meta_keywords' => 'Магазины,поставщики,партнеры',
            'h1' => 'Магазины',
            'description' => 'Поставщики и партнеры'
        ]);

        $view->getContent()->addParams([
            'items' => $items = $this->app->managers->vendors->getNonEmptyGroupedByFirstCharObjects(),
            'chars' => array_keys($items)
        ]);

        return $this->app->response->setHTML(200, $view);
    }

    /**
     * @return bool
     * @throws NotFound
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function actionBuy()
    {
        return $this->actionGo('item', $this->app->request->get('id'), $this->app->request->get('source'));
    }

    /**
     * @param null $type
     * @param null $id
     * @param null $source
     *
     * @return Response
     * @throws NotFound
     */
    public function actionGo($type = null, $id = null, $source = null)
    {
        if (!$type = $type ?: $this->app->request->get('type')) {
            throw (new BadRequest)->setInvalidParam('type');
        }

        if (!$id = $id ?: $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        if ('item' == $type) {
            if (!$holder = $this->app->managers->items->find($id)) {
                if ($archive = $this->app->managers->archiveItems->find($id)) {
                    $this->app->views->getLayout()
                        ->addMessage(implode(' ', [
                            'Товар снят с продажи, извините за неудобства.',
                            'Пожалуйста, обратите внимание на похожие модели'
                        ]), Layout::MESSAGE_WARNING);

                    $this->app->request->redirect($this->app->managers->items->getLink($archive), 301);
                } elseif ($idTo = $this->app->managers->itemRedirects->getByIdFrom($id)) {
                    if ($itemTo = $this->app->managers->items->find($idTo)) {
                        $this->app->request->redirect($this->app->managers->items->getLink($itemTo), 301);
                    }

                    throw new NotFound;
                } else {
                    throw new NotFound;
                }
            }
        } elseif ('shop' == $type) {
            if (!$holder = $this->app->managers->vendors->find($id)) {
                throw new NotFound;
            }

            if (!$holder->isActive()) {
                $this->app->views->getLayout()
                    ->addMessage(implode(' ', [
                        'Магазин более не доступен, извините за неудобства.',
                        'Пожалуйста, обратите внимание на другие магазины'
                    ]), Layout::MESSAGE_WARNING);

                $this->app->request->redirect($this->app->router->makeLink('default', ['action' => 'shops']), 301);
            }
        } elseif ('stock' == $type) {
            if (!$holder = $this->app->managers->stock->find($id)) {
                throw new NotFound;
            }

            if (!$holder->isActive()) {
                $this->app->views->getLayout()
                    ->addMessage(implode(' ', [
                        'Акция более не доступна, извините за неудобства.',
                        'Пожалуйста, обратите внимание на другие акционные предложения'
                    ]), Layout::MESSAGE_WARNING);

                $this->app->request->redirect($this->app->router->makeLink('default', ['action' => 'stock']), 301);
            }
        } else {
            throw (new NotFound)->setNonExisting('type');
        }

        if ($this->app->configMaster) {
            /** @var GoLinkBuilderInterface $manager */
            $manager = $this->app->managers->getByEntity($holder);
            $link = $this->app->configMaster->domains->master . $manager->getGoLink($holder, 'slave');
            $this->app->request->redirect($link, 302);
        }

        if (!$this->app->request->has('source')) {
            try {
                $this->app->analytics->logGoHit($holder);
            } catch (\Exception $ex) {
                $this->app->services->logger->makeException($ex);
            }
        }

        /** @var PartnerLinkHolderInterface $holder */

        if (!$link = $holder->getPartnerLink()) {
            $this->app->services->logger->make('invalid partner link(type=' . $type . ', id=' . $id . ')',
                Logger::TYPE_ERROR);
            $this->app->views->getLayout()->addMessage('Предложение не доступно, извините за неудобства.',
                Layout::MESSAGE_WARNING);
            $this->app->request->redirect($this->app->request->getReferer(), 301);
        }

        if ($this->app->isDev()) {
            die('Redirecting to "' . $link . '"...');
        }

        $tmp = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{$this->app->getSite()}</title>
<meta http-equiv="refresh" content="0; URL={$link}">
<script type="text/javascript">window.location.href = '{$link}'</script>
</head>
<body></body>
</html>
HTML;

        return $this->app->response
//            ->setHttpResponseCode(200)
//            ->setRawHeader('200 OK')
            ->setBody($tmp)//            ->send(true)
            ;
    }

    /**
     * @return Response|bool
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function actionCatalog()
    {
        $key = 'catalog';

        $this->app->managers->categories->clear();

        $this->app->services->mcms->prefetch([
            $this->app->managers->categories->getAllIDsCacheKey(),
            $this->app->managers->pagesRegular->getMenuCacheKey(),
        ]);

        //cache all categories...
        $this->app->managers->categories->findAll();

        $this->app->request->set('uri', null);

        $uriManager = new CatalogUriManager($this->app);

        if (!$uri = $uriManager->createFromRequest($this->app->request)) {
            return false;
        }

        $this->app->analytics->logPageHit($key);

        $view = $this->app->views->getLayout();

        $mobile = $this->app->request->getDevice()->isMobile();

        if ((!$mobile) && $gridBanner = $this->app->ads->findBanner(LargeRectangleAd::class, 'catalog-grid', [], $view)) {
            $uri->getSRC()->setLimit($uri->getSRC()->getLimit() - ItemsGrid::getBannerCellsCount());
        }

        $this->app->seo->manageCatalogPage($uri, $view, [
            'meta_title' => '{category} {sport} {size_plus} {tags} {sizes} {brands} {colors}  {materials} {seasons} {countries} {sales} {vendors} купить {prices} с доставкой по РФ, выбор среди {total} предложений. {page_long}',
            'meta_description' => 'Купить {category_long} {sport_long} {size_plus_long} {tags_long} {brands_long}. {sizes_long}. {colors_long}. {sales_long}. {materials_long}. {seasons_long}. {countries_long}. {vendors_long}. {prices}. Более {total} предложений ✔ Доставка по РФ ✔ Низкие цены ✔ Возврат ✔ Комфортная и надежная покупка! *** Тел. {phone} ***. {page_long}',
            'meta_keywords' => '{category_keys},{sport_keys},{size_plus_keys},{tags_keys},{brands_keys},купить,{colors_keys},{sales_keys},{seasons_keys},{countries_keys},{sizes_keys},{vendors_keys}',
            'h1' => '{category} {sport} {size_plus} {tags} {brands} {sizes} {colors} {materials} {seasons} {countries} {vendors} {sales}',
            'description' => '{category} {sport} {size_plus} {tags} {brands} {sizes} {colors} {materials} {seasons} {countries} {vendors} {sales}',
        ]);
        $this->app->analytics->logCatalogPageHit($uri);

        $filtersNames = array_diff($this->app->managers->catalog->getComponentsPKs(), [Category::getPk()]);

        $items = $uri->getSRC()->getItems($total);

        $content = $view->setContentByTemplate('@snowgirl-shop/catalog.phtml', [
            'uri' => $uri->output(),
            'uriParams' => $uriParams = $uri->getParamsArray(),
            'h1' => $view->getH1(),
            'currency' => $this->getCurrencyObject(),
            'filters' => $filters = $uri->getParamsByNames($filtersNames),
            'prices' => $prices = $uri->getParamsByNames([URI::PRICE_FROM, URI::PRICE_TO]),
            'uriFilterParams' => $uri->getParamsByTypes('filter'),
            'uriViewParams' => $uri->getParamsByTypes('view'),
            'categories' => $this->app->managers->categories->makeTreeHtml($uri),
            'sortValues' => SRC::getOrderValues(),
            'sortValuesNames' => SEO::getOrderValuesToTexts(true),
            'typesNames' => SEO::getTypesToTexts(true),
            'types' => $types = array_keys($uri->getParamsByNames(URI::TYPE_PARAMS)),
//            'hasAppliedFilters' => $hasAppliedFilters = $uriParams[URI::QUERY] || count($filters) > 0 || count($prices) > 0 || count($types) > 0,
            'hasAppliedFilters' => $hasAppliedFilters = count($filters) > 0 || count($prices) > 0 || count($types) > 0,
            'hasAppliedSorting' => $hasAppliedSorting = !!$uriParams[URI::ORDER],
            'orFiltersKeys' => $uri->getOrParamsKeysByNames($filtersNames),
            'hasItems' => $total > 0,
            'totalCount' => $total,
            'siteName' => $this->app->getSite()
        ]);

        //@todo...
        if (!$mobile) {
            $content->showValues = SRC::getShowValues($this->app);
        }

        if ($content->hasItems) {
            $content->itemsGrid = $this->app->views->items([
                'items' => $items,
                'offset' => $uri->getSRC()->getOffset(),
                'propName' => $content->title,
                'propUrl' => (new URI($uriParams))->_unset(URI::PAGE_NUM)->output(),
                'propTotal' => $content->totalCount,
                'banner' => isset($gridBanner) ? $gridBanner : null
            ], $view);

            $pagerUri = $uri->copy();

            $pager = $this->app->views->pager([
                'link' => $pagerUri->set(URI::PAGE_NUM, '{' . URI::PAGE_NUM . '}')->output(),
                'total' => $total,
                'size' => $uri->getSRC()->getLimit(),
                'page' => $uri->getSRC()->getPageNum(),
                'per_set' => 5,
                'param' => URI::PAGE_NUM,
//                'attrs' => 'rel="nofollow"',
                'statistic' => false
            ], $view);

            if ($pager->isOk() && !$mobile) {
                $content->itemsPager = (string)$pager;
            }

            $content->addParams([
//                'showAjaxPager' => !$pager->isLastPage(),
                'showAjaxPager' => $pager->isOk()
            ]);

            $this->app->seo->manageCatalogPager($pagerUri, $pager, $view);
        } else {
            $content->itemsEmptyVariants = $uriManager->getOtherVariants($uri, function ($uri) use ($view) {
                /** @var URI $uri */
                return (object)[
                    'href' => $href = $uri->output(),
                    'text' => $text = $uri->getSEO()->getParam('h1', [
                        'category' => ($tmp = $uri->get(Category::getPk()))
                            ? $this->app->managers->categories->find($tmp)->getName()
                            : $this->app->translator->makeText('catalog.catalog')
                    ]),
                    'grid' => $this->app->views->items([
                        'items' => array_slice($uri->getSRC()->getItems($total), 0, 4),
                        'itemTemplate' => 'catalog',
                        'propName' => $text,
                        'propUrl' => $href,
                        'propTotal' => $total
                    ], $view),
                    'count' => $total
                ];
            });

            $content->itemsPager = '';
        }

        $view->addJsConfig('searchQuery', $content->title);

        if ($categoryId = $uri->get(Category::getPk())) {
            $category = $this->app->managers->categories->find($categoryId);
        } else {
            $category = null;
        }

        $this->app->seo->manageCatalogBreadcrumbs($uri, count($filters) + count($types), $view);

        $content->addParams([
            'category' => $category,
            'hasApplied' => $categoryId || $hasAppliedFilters || $hasAppliedSorting,
            'showTags' => $categoryId && $this->app->managers->categories->isLeaf($category),
            'vkontakteLike' => $this->app->views->vkontakteLike($view)->stringify(),
            'facebookLike' => $this->app->views->facebookLike($view)->stringify(),
            'asyncFilters' => $this->app->config->catalog->async_filters(false)
        ]);

        if ($content->hasItems) {
            $content->addParams([
                'filtersCounts' => $this->getFiltersCountsObject(),
                'filtersTypesView' => $this->actionGetCatalogFiltersTypesView($uri, $content->asyncFilters),
                'filtersTagsView' => $content->showTags ? $this->actionGetCatalogFiltersTagsView($uri, $content->asyncFilters) : null,
                'filtersBrandsView' => $this->actionGetCatalogFiltersBrandsView($uri, $content->asyncFilters, $brands),
                'filtersCountriesView' => $this->actionGetCatalogFiltersCountriesView($uri, $content->asyncFilters),
                'filtersVendorsView' => $this->actionGetCatalogFiltersVendorsView($uri, $content->asyncFilters),
                'filtersPricesView' => $this->actionGetCatalogFiltersPricesView($uri, $content->asyncFilters),
                'filtersColorsView' => $this->actionGetCatalogFiltersColorsView($uri, $content->asyncFilters),
                'filtersSeasonsView' => $this->actionGetCatalogFiltersSeasonsView($uri, $content->asyncFilters),
                'filtersMaterialsView' => $this->actionGetCatalogFiltersMaterialsView($uri, $content->asyncFilters),
                'filtersSizesView' => $this->actionGetCatalogFiltersSizesView($uri, $content->asyncFilters),
            ]);

            if (!$mobile) {
                $tmpParams = $uri->getParamsByTypes('filter');

                if (!Arrays::diffByKeysArray($tmpParams, ['category_id', URI::SALES])) {
                    //@todo replace filter with sort

                    if (isset($tmpParams['category_id'])) {
                        $category = $this->app->managers->categories->find($tmpParams['category_id']);

                        if ($this->app->managers->categories->isLeaf($category)) {
                            //@todo available only
                            //@todo retrieve top category-tags pairs
//                        $popularCategoryTags = [];
                        } else {
                            //@todo available only
                            $popularCategories = $this->app->managers->categories->getDirectChildrenObjects($category);
                        }
                    } else {
                        $popularCategories = $this->app->managers->categories->getRootObjects();
                    }

                    $tmpUri = new URI($tmpParams);

                    if (isset($popularCategories)) {
                        $popularCategories = array_filter($popularCategories, function ($category) {
                            return 0 < $this->app->managers->categories->getItemsCount($category);
                        });

                        $this->app->managers->categories->sortByRating($popularCategories);

                        //@todo cache...
                        $content->categoriesGrid = $this->app->views->categories([
                            //hidden coz: 1) items counts 2) increase clicks
//                        'uri' => $tmpUri->copy()->_unset('category_id'),
                            'items' => array_slice($popularCategories, 0, 6)
                        ], $content);
                    }

                    //@todo cache...
                    $content->brandsGrid = $this->app->views->brands([
                        'uri' => $tmpUri,
                        'items' => isset($brands) && !$content->asyncFilters
                            ? array_slice($brands, 0, 12)
                            : $this->app->managers->brands->clear()
                                ->setOrders(['rating' => SORT_DESC])
                                ->setLimit(12)
                                ->getObjectsByUri($tmpUri)
                    ], $content);
                }
            }
        }

        if ((!$mobile) && $h1Banner = $this->app->ads->findBanner(LongHorizontalAd::class, 'catalog-title', [], $view)) {
            $content->h1Banner = $h1Banner;
        }

        return $this->app->response->setHTML(200, $view);
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function actionGetCatalogItems()
    {
        $uri = new URI($this->app->request->getParams());
        $src = $uri->getSRC();

        $referer = $this->app->request->getReferer();
        $path = $this->app->request->getPathInfoByUri($referer);

        if ($this->app->router->getRoute('item')->match($path)) {
            $bannerKey = 'item-catalog-grid';
        } else {
            $bannerKey = 'catalog-grid';
        }

        if ((!$this->app->request->getDevice()->isMobile()) && $gridBanner = $this->app->ads->findBanner(LargeRectangleAd::class, $bannerKey, [Ads::GOOGLE, Ads::YANDEX])) {
            $src->setLimit($src->getLimit() - ItemsGrid::getBannerCellsCount());
        }

        return $this->app->response->setJSON(200, [
            'view' => $this->app->views->items([
                'items' => $src->getItems($total),
                'offset' => $src->getOffset(),
                'banner' => isset($gridBanner) ? $gridBanner : null
            ])->stringifyPartial('raw'),
            'pageUri' => $uri->output(),
            'isLastPage' => $src->isLastPage()
        ]);
    }

    public function actionGetCatalogFiltersTypesView(URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($this->app->request->getParams());
        }

        if ($async) {
            //@todo...
            $types = [];
        } else {
            $types = array_keys($this->app->managers->items->clear()->getTypesByUri($uri));
        }

        if ($types) {
            $view = $this->app->views->get('@snowgirl-shop/catalog/filters/types.phtml', [
                'uriParams' => $uri->getParams(),
                'typesNames' => SEO::getTypesToTexts(true),
                'types' => $types
            ]);

            $view = (string)$view;
            $view = trim($view);
        } else {
            $view = null;
        }

        if ($ajax) {
            return $this->app->response->setJSON(200, [
                'view' => $view
            ]);
        }

        return $view;
    }

    public function actionGetCatalogFiltersTagsView(URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($this->app->request->getParams());
        }

        if ($async) {
            //@todo...
            $tags = [];
        } else {
            $tags = $this->app->managers->tags->clear()->setLimit($this->getFiltersCountsObject()['tag'])->getObjectsByUri($uri);
        }

        if ($tags) {
            $view = $this->app->views->get('@snowgirl-shop/catalog/filters/tags.phtml', [
                'uriParams' => $uri->getParams(),
                'tags' => $tags
            ]);

            $view = (string)$view;
            $view = trim($view);
        } else {
            $view = null;
        }

        if ($ajax) {
            return $this->app->response->setJSON(200, [
                'view' => $view
            ]);
        }

        return $view;
    }

    public function actionGetCatalogFiltersBrandsView(URI $uri = null, $async = false, &$brands = null)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($this->app->request->getParams());
        }

        if ($async) {
            //@todo...
            $brands = [];
        } else {
            $brands = $this->app->managers->brands->clear()->setLimit($this->getFiltersCountsObject()['brand'])->getObjectsByUri($uri);
        }

        if ($brands) {
            $view = $this->app->views->get('@snowgirl-shop/catalog/filters/brands.phtml', [
                'uriParams' => $uri->getParams(),
                'brands' => $brands
            ]);

            $view = (string)$view;
            $view = trim($view);
        } else {
            $view = null;
        }

        if ($ajax) {
            return $this->app->response->setJSON(200, [
                'view' => $view
            ]);
        }

        return $view;
    }

    public function actionGetCatalogFiltersCountriesView(URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($this->app->request->getParams());
        }

        if ($async) {
            //@todo...
            $countries = [];
        } else {
            $countries = $this->app->managers->countries->clear()->setLimit($this->getFiltersCountsObject()['country'])->getObjectsByUri($uri);
        }

        if ($countries) {
            $view = $this->app->views->get('@snowgirl-shop/catalog/filters/countries.phtml', [
                'uriParams' => $uri->getParams(),
                'countries' => $countries
            ]);

            $view = (string)$view;
            $view = trim($view);
        } else {
            $view = null;
        }

        if ($ajax) {
            return $this->app->response->setJSON(200, [
                'view' => $view
            ]);
        }

        return $view;
    }

    public function actionGetCatalogFiltersVendorsView(URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($this->app->request->getParams());
        }

        if ($async) {
            //@todo...
            $vendors = [];
        } else {
            $vendors = $this->app->managers->vendors->clear()->setLimit($this->getFiltersCountsObject()['vendor'])->getObjectsByUri($uri);
        }

        if ($vendors) {
            $view = $this->app->views->get('@snowgirl-shop/catalog/filters/vendors.phtml', [
                'uriParams' => $uri->getParams(),
                'vendors' => $vendors
            ]);

            $view = (string)$view;
            $view = trim($view);
        } else {
            $view = null;
        }

        if ($ajax) {
            return $this->app->response->setJSON(200, [
                'view' => $view
            ]);
        }

        return $view;
    }

    public function actionGetCatalogFiltersPricesView(URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($this->app->request->getParams());
        }

        if ($async) {
            //@todo...
            $prices = [];
        } else {
            $prices = $this->app->managers->items->clear()->getPricesByUri($uri);
        }

        if ($prices) {
            $view = $this->app->views->get('@snowgirl-shop/catalog/filters/prices.phtml', [
                'uriParams' => $uri->getParams(),
                'priceFrom' => $uri->get(URI::PRICE_FROM),
                'priceTo' => $uri->get(URI::PRICE_TO),
                'currency' => $this->getCurrencyObject(),
                'prices' => $prices
            ]);

            $view = (string)$view;
            $view = trim($view);
        } else {
            $view = null;
        }

        if ($ajax) {
            return $this->app->response->setJSON(200, [
                'view' => $view
            ]);
        }

        return $view;
    }

    public function actionGetCatalogFiltersColorsView(URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($this->app->request->getParams());
        }

        if ($async) {
            //@todo...
            $colors = [];
        } else {
            $colors = $this->app->managers->colors->clear()->setLimit($this->getFiltersCountsObject()['color'])->getObjectsByUri($uri, false);
        }

        if ($colors) {
            $view = $this->app->views->get('@snowgirl-shop/catalog/filters/colors.phtml', [
                'uriParams' => $uri->getParams(),
                'colors' => $colors
            ]);

            $view = (string)$view;
            $view = trim($view);
        } else {
            $view = null;
        }

        if ($ajax) {
            return $this->app->response->setJSON(200, [
                'view' => $view
            ]);
        }

        return $view;
    }

    public function actionGetCatalogFiltersSeasonsView(URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($this->app->request->getParams());
        }

        if ($async) {
            //@todo...
            $seasons = [];
        } else {
            $seasons = $this->app->managers->seasons->clear()->setLimit($this->getFiltersCountsObject()['season'])->getObjectsByUri($uri);
        }

        if ($seasons) {
            $view = $this->app->views->get('@snowgirl-shop/catalog/filters/seasons.phtml', [
                'uriParams' => $uri->getParams(),
                'seasons' => $seasons
            ]);

            $view = (string)$view;
            $view = trim($view);
        } else {
            $view = null;
        }

        if ($ajax) {
            return $this->app->response->setJSON(200, [
                'view' => $view
            ]);
        }

        return $view;
    }

    public function actionGetCatalogFiltersMaterialsView(URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($this->app->request->getParams());
        }

        if ($async) {
            //@todo...
            $materials = [];
        } else {
            $materials = $this->app->managers->materials->clear()->setLimit($this->getFiltersCountsObject()['material'])->getObjectsByUri($uri);
        }

        if ($materials) {
            $view = $this->app->views->get('@snowgirl-shop/catalog/filters/materials.phtml', [
                'uriParams' => $uri->getParams(),
                'materials' => $materials
            ]);

            $view = (string)$view;
            $view = trim($view);
        } else {
            $view = null;
        }

        if ($ajax) {
            return $this->app->response->setJSON(200, [
                'view' => $view
            ]);
        }

        return $view;
    }

    public function actionGetCatalogFiltersSizesView(URI $uri = null, $async = false)
    {
        if ($ajax = null === $uri) {
            $uri = new URI($this->app->request->getParams());
        }

        if ($async) {
            //@todo...
            $sizes = [];
        } else {
            $sizes = $this->app->managers->sizes->clear()->setLimit($this->getFiltersCountsObject()['size'])->getObjectsByUri($uri);
        }

        if ($sizes) {
            $view = $this->app->views->get('@snowgirl-shop/catalog/filters/sizes.phtml', [
                'uriParams' => $uri->getParams(),
                'sizes' => $sizes
            ]);

            $view = (string)$view;
            $view = trim($view);
        } else {
            $view = null;
        }

        if ($ajax) {
            return $this->app->response->setJSON(200, [
                'view' => $view
            ]);
        }

        return $view;
    }

    /**
     * @todo create common view for attr filter record, use it in main template and here - then return view...
     *
     * @return Response
     */
    public function actionGetAttrSuggestions()
    {
        $table = $this->app->request->get('name');
        $prefix = $this->app->request->get('prefix');
        $query = $this->app->request->get('q');

        $sizeMax = 300;
        $size = $this->app->request->get('per_page', $sizeMax);
        $size = min($sizeMax, $size);

        $page = $this->app->request->get('page');

        $uri = new URI($this->app->request->getParams());

        /** @var ItemAttrManager $manager */
        $manager = $this->app->managers->getByTable($table);

        $pk = $manager->getEntity()->getPk();

        $output = [];

        $uriParams = $uri->getParamsArray();

        foreach ($manager->clear()
                     ->setOffset(($page - 1) * $size)
                     ->setLimit($size)
                     ->getObjectsByUriAndQuery($uri, $query, true, $prefix) as $item) {
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

        return $this->app->response->setJSON(200, $output);
    }

    /**
     * @todo check if attributes objects exists... (category,brand,etc...)
     * @todo ..if not exists - manage such situation (category: find nearest category, brand: find brand from item...)
     * @return Response|bool
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function actionItem()
    {
        $key = 'item';

        $this->app->services->mcms->prefetch([
            $this->app->managers->categories->getAllIDsCacheKey(),
            $this->app->managers->pagesRegular->getItemCacheKey($key),
            $this->app->managers->pagesRegular->getMenuCacheKey(),
//            $this->app->managers->vendors->getCacheKeyById($item->getVendorId())
        ]);

        //cache all categories...
        $this->app->managers->categories->findAll();

        $this->app->request->set('uri', null);

        $uriManager = new ItemUriManager($this->app);

        if (!$uri = $uriManager->createFromRequest($this->app->request)) {
            return false;
        }

        $this->app->analytics->logPageHit($key);

        $view = $this->app->views->getLayout();

        $this->app->seo->manageItemPage($uri, $view, [
            'meta_title' => '{name} – купить за {price} руб. в интернет-магазине: цена, фото и описание. Арт. {upc}',
            'meta_description' => '{description} ✔ интернет-магазин {site} *** Тел: {phone} ✔ Доставка ✔ Гарантия ✔ Лучшие цены! Артикул № {upc}',
            'meta_keywords' => 'купить, {keywords}, женские, распродажа, дешево',
            'h1' => '{name}',
            'description' => '{name}',
        ]);
        $this->app->analytics->logItemPageHit($uri);

        $item = $uri->getSRC()->getItem();

        $this->app->managers->items->clear();

        $content = $view->setContentByTemplate('@snowgirl-shop/item.phtml', [
            'item' => $item,
            'images' => $this->app->managers->items->getImages($item),
            'h1' => $uri->getSEO()->getParam('h1'),
            'currency' => $this->getCurrencyObject(),
            'tags' => $this->app->managers->items->getTagsURI($item),
            'types' => $this->app->managers->items->getTypes($item),
            'attrs' => $this->app->managers->items->getAttrs($item),
            'deviceDesktop' => $this->app->request->getDevice()->isDesktop(),
            'archive' => $archive = $item->get('archive'),
            'typeOwn' => (!$archive) && ($source = $this->app->managers->items->getImportSource($item)) && (Source::TYPE_OWN == $source->getType()),
            'sharer' => $this->app->views->sharer($view)->stringify()
        ]);

        $relatedUri = $this->app->managers->items->getRelatedCatalogURI($item)
            ->set(URI::PER_PAGE, Helper::roundUpToAny(SRC::getDefaultShowValue($this->app), 5))
            ->set(URI::EVEN_NOT_STANDARD_PER_PAGE, true);

        if ((!$this->app->request->getDevice()->isMobile()) && $gridBanner = $this->app->ads->findBanner(LargeRectangleAd::class, 'item-catalog-grid', [], $view)) {
            $relatedUri->getSRC()->setLimit($relatedUri->getSRC()->getLimit() - ItemsGrid::getBannerCellsCount());
        }

        $relatedItems = $relatedUri->getSRC()->getItems($total);

        $content->hasRelatedItems = $total > 0;

        if ($content->hasRelatedItems) {
            $relatedItemsH1 = $relatedUri->getSEO()->getParam('h1');

            $content->relatedItemsGrid = $this->app->views->items([
                'h21' => 'Похожие ' . mb_strtolower($relatedItemsH1),
                'items' => $relatedItems,
                'propName' => $relatedItemsH1,
                'propUrl' => $relatedUri->copy()->_unset(URI::PER_PAGE)->output(),
                'propTotal' => $total,
                'banner' => isset($gridBanner) ? $gridBanner : null
            ], $view);

            $content->relatedItemsPager = $total > $relatedUri->getSRC()->getLimit();
        }

        $content->addParams([
            'relatedUriFilterParams' => $relatedUri->getParamsByTypes('filter'),
            'relatedUriViewParams' => $relatedUri->getParamsByTypes('view')
        ]);

        $this->app->seo->manageItemBreadcrumbs($uri, $view);

        return $this->app->response->setHTML(200, $view);
    }

    /**
     * @return Response
     * @throws NotFound
     */
    public function actionCheckItemIsInStock()
    {
        if (!$this->app->request->isGet()) {
            throw (new MethodNotAllowed)->setValidMethod('get');
        }

        if (!$id = $this->app->request->get('id')) {
            throw (new BadRequest)->setInvalidParam('id');
        }

        if (!$item = $this->app->managers->items->find($id)) {
            throw new NotFound;
        }

        if ($item->isInStock()) {
            $answer = true;
        } else {
            $answer = $this->app->managers->items->checkRealIsInStock($item);

            if (true === $answer) {
                $item->setIsInStock(true);
                $this->app->managers->items->updateOne($item);

                if ($this->app->services->mcms->isOn()) {
                    //do manual cache coz of ftdbms storage
                    $this->app->services->mcms->set(
                        $this->app->managers->items->getCacheKeyByEntity($item),
                        $item->getAttrs()
                    );
                }
            }
        }

        return $this->app->response->setJSON(200, [
//            'in_stock' => true,
//            'in_stock' => false,
//            'in_stock' => null,
            'in_stock' => $answer
        ]);
    }

    /**
     * @return Response
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function actionStock()
    {
        $view = $this->processTypicalPage('stock', [
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
            'items' => $this->app->managers->stock->clear()
                ->setWhere(['is_active' => 1])
                ->setOrders(['stock_id' => SORT_DESC])
                ->setLimit(7)
                ->cacheOutput(Stock::CACHE_STOCK_PAGE)
                ->getObjects(),
            'manager' => $this->app->managers->stock,
            'vkontakteLike' => $this->app->views->vkontakteLike($view)->stringify(),
            'facebookLike' => $this->app->views->facebookLike($view)->stringify()
        ]);

        if ((!$this->app->request->getDevice()->isMobile()) && $banner = $this->app->ads->findBanner(LongHorizontalAd::class, 'stock', [], $view)) {
            $content->banner = $banner;
        }

        return $this->app->response->setHTML(200, $view);
    }

    /**
     * @return Response
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function actionContacts()
    {
        $view = $this->processTypicalPage('contacts', [
            'meta_title' => 'Контакты',
            'meta_description' => 'Как нас найти',
            'meta_keywords' => 'Контакты, телефоны, связь',
            'h1' => 'Контакты',
            'description' => 'Наши контакты. Как добраться'
        ]);

        $content = $view->getContent();

        $config = $this->app->config->site;

        $content->addParams([
            'contacts' => [
                'email' => $config->email,
                'phone' => $config->phone,
                'time' => $config->time,
                'address' => $config->address
            ],
            'map' => $this->app->views->googleMap(['center' => [
                'latitude' => $config->latitude,
                'longitude' => $config->longitude
            ]], $view)->stringify()
        ]);

        $form = $this->app->views->contactForm([], $view);

        if ($itemId = $this->app->request->get('item_id')) {
            if ($item = $this->app->managers->items->find($itemId)) {
                if ($sizeId = $this->app->request->get('size_id')) {
                    $size = $this->app->managers->sizes->find($sizeId);
                } else {
                    $size = '';
                }

                $href = $this->app->managers->items->getLink($item, [], 'master');
                $size = $size->getName();
                $qnt = $this->app->request->get('quantity');

                $body = T('contacts.buy_item', $href, $size, $qnt);
                $form->setParam('body', $body);
            }
        }

        if ($this->app->request->isPost()) {
            $isOk = $form->process($this->app->request, $msg);
            $view->addMessage($msg, $isOk ? Layout::MESSAGE_SUCCESS : Layout::MESSAGE_ERROR);
        }

        $content->addParams([
            'form' => $form->stringify()
        ]);

        return $this->app->response->setHTML(200, $view);
    }

    /**
     * @return Response
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function actionOrder()
    {
        if (!$this->app->request->isGet()) {
            throw (new MethodNotAllowed)->setValidMethod('get');
        }

        $view = $this->processTypicalPage('order', [
            'meta_title' => 'Корзина интернет-магазина {site}',
            'meta_description' => 'Корзина товаров в интернет магазине {site}. Оформление заказа',
            'meta_keywords' => 'Корзина, заказ, оплата, покупка, оформление',
            'h1' => 'Корзина',
            'description' => 'Офорление заказа'
        ]);

        $form = $this->app->views->orderForm([], $view);

        if ($this->app->request->isPost()) {
            $isOk = $form->process($this->app->request, $msg);
            $view->addMessage($msg, $isOk ? Layout::MESSAGE_SUCCESS : Layout::MESSAGE_ERROR);
            $this->app->request->redirectToRoute('catalog');
        }

        $cart = $this->app->request->getSession()->get('cart', []);

        $itemToParams = [];

        array_walk($cart, function ($item) use (&$itemToParams) {
            $itemToParams[$item['item_id']] = [$item['size_id'], $item['quantity']];
        });

        $cart = array_keys($itemToParams);
        $visited = $this->app->request->getSession()->get('visited', []);

        $items = $this->app->managers->items->findMany(array_merge($cart, $visited));

        $sizePk = Size::getPk();

        $view->getContent()->addParams([
            'cart' => array_map(function ($id) use ($items, $itemToParams, $sizePk) {
                /** @var Item[] $items */
                return $items[$id]->setRawVar('size', $itemToParams[$id][0])
                    ->setRawVar('quantity', $itemToParams[$id][1])
                    ->setRawVar('sizes', $this->app->managers->items->getAttrs($items[$id], [$sizePk])[$sizePk]);
            }, $cart),
            'visited' => array_map(function ($id) use ($items) {
                /** @var Item[] $items */
                return $items[$id];
            }, $visited),
            'form' => $form->stringify()
        ]);

        $view->setNoIndexNoFollow();

        return $this->app->response->setNoIndexNoFollow()
            ->setHTML(200, $view);
    }

    protected function getCurrencyObject()
    {
        return (object)[
            'iso' => $iso = $this->app->config->catalog->currency('RUB'),
            'text' => $this->app->translator->makeText('catalog.currency_' . $iso)
        ];
    }

    protected function getFiltersCountsObject()
    {
        return $this->app->config->catalog->filters_counts([
            'tag' => 10,
            'brand' => 100,
            'country' => null,
            'vendor' => null,
            'color' => 36,
            'season' => null,
            'material' => 100,
            'size' => 100
        ]);
    }

    public function actionTranslate()
    {
        $text = $this->app->request->get('text');
        $source = $this->app->request->get('source');
        $target = $this->app->request->get('target');

        if ($tmp = $this->app->utils->catalog->translateRaw($text, $source, $target)) {
            if (is_array($tmp) && isset($tmp['text'])) {
                $view = $tmp['text'];
            } else {
                $view = null;
            }
        } else {
            $view = null;
        }

        return $this->app->response->setHTML(200, $view);
    }
}
