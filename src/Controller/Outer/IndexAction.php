<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/15/19
 * Time: 12:18 AM
 */

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\AddVerificationsTrait;
use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Controller\Outer\ProcessTypicalPageTrait;
use SNOWGIRL_CORE\View\Layout\Outer as OuterLayout;
use SNOWGIRL_SHOP\App\Web as App;
use SNOWGIRL_SHOP\Catalog\SEO;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Entity\Brand;
use SNOWGIRL_CORE\View\Widget\Ad\LongHorizontal as LongHorizontalAd;

class IndexAction
{
    use PrepareServicesTrait;
    use ProcessTypicalPageTrait;
    use AddVerificationsTrait;
    use GetCurrencyObjectTrait;

    /**
     * @param App $app
     *
     * @throws \SNOWGIRL_CORE\Exception
     * @throws \Exception
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $categories = $app->managers->categories;

        //cache all categories...
        $categories->findAll();

        /** @var OuterLayout $view */
        $view = $this->processTypicalPage($app, 'index', [
            'site' => $site = $app->getSite(),
            'meta_title' => 'Лучший женский каталог',
            'meta_description' => 'Лучший женский каталог',
            'meta_keywords' => 'Каталог,женский,огромный,выбор',
            'h1' => 'Женский каталог',
            'description' => 'Женский каталог, огромный выбор',
//            'image' => $image = isset($data['topBanners'][0]) ? $data['topBanners'][0]->getImage() : null
        ]);

        $this->addVerifications($app, $view);

        $content = $view->getContent();

        $view->about = (string)$app->views->get('@shop/index/about.phtml', [
            'h1' => $content->title,
            'content' => $content->description,
            'site' => $site,
            'uri' => $app->router->makeLink('default'),
//            'image' => $image
        ]);

//        $nonEmptyImage = new Expr('`image` IS NOT NULL');
        $ratingDesc = ['rating' => SORT_DESC];

        $content->addParams([
            'carousel' => $app->views->carousel(['items' => $app->managers->banners->clear()
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
            'brands' => $app->managers->brands->clear()
//                ->setWhere($nonEmptyImage)
                ->setOrders($ratingDesc)
                ->setLimit($brandsPerBlock * $repeatTimes)
                ->cacheOutput(true)
                ->getObjects(),
            'itemsPerBlock' => $itemsPerBlock = 12,
            'currency' => $this->getCurrencyObject($app),
            'items' => $app->managers->items->clear()
//            ->setStorage(Manager::STORAGE_FTDBMS)
                ->setWhere(['is_in_stock' => 1])
                ->setOrders($ratingDesc)
                ->setLimit($itemsPerBlock * $repeatTimes)
                ->cacheOutput(true)
                ->getObjects(),
            'menu' => array_values($app->managers->pagesRegular->getMenu()),
            'vkontakteLike' => $app->views->vkontakteLike($view)->stringify(),
            'facebookLike' => $app->views->facebookLike($view)->stringify()
        ]);

        $app->managers->items->addLinkedObjects($content->items, Brand::class);

        if ($app->request->getDevice()->isMobile()) {
            $catalogNav = [];

            $categoriesList = $categories->getRootObjects();
            $categories->sortByRating($categoriesList);

            foreach (array_slice($categoriesList, 0, 3) as $category) {
                $catalogNav[$category->getName()] = $app->managers->categories->getLink($category);
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
            if ($banner = $app->ads->findBanner(LongHorizontalAd::class, 'index', [], $view)) {
                $content->banner = $banner;
            }

            $content->advantages = [
                ['Все популярные предложения в одном месте', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACkAAAApCAYAAACoYAD2AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA+tpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMDY3IDc5LjE1Nzc0NywgMjAxNS8wMy8zMC0yMzo0MDo0MiAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczpkYz0iaHR0cDovL3B1cmwub3JnL2RjL2VsZW1lbnRzLzEuMS8iIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIiB4bWxuczpzdFJlZj0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL3NUeXBlL1Jlc291cmNlUmVmIyIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ0MgMjAxNSAoV2luZG93cykiIHhtcDpDcmVhdGVEYXRlPSIyMDE2LTExLTA3VDExOjAyOjE0KzAyOjAwIiB4bXA6TW9kaWZ5RGF0ZT0iMjAxNi0xMS0wN1QxMTowMjoxOSswMjowMCIgeG1wOk1ldGFkYXRhRGF0ZT0iMjAxNi0xMS0wN1QxMTowMjoxOSswMjowMCIgZGM6Zm9ybWF0PSJpbWFnZS9wbmciIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6RTMyN0E4MEVBNEM4MTFFNkJDMDdDOEZDQTNBQTIxMjgiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6RTMyN0E4MEZBNEM4MTFFNkJDMDdDOEZDQTNBQTIxMjgiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDpFMzI3QTgwQ0E0QzgxMUU2QkMwN0M4RkNBM0FBMjEyOCIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDpFMzI3QTgwREE0QzgxMUU2QkMwN0M4RkNBM0FBMjEyOCIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PqodeMYAAAKmSURBVHja7JhNSFVBFMff9fXhS0kzgigDcZEEgUTRKhCiRRAtaldpltmXim00WkSbCFFB+qAvNFpV0MKtKyEoUQufH7UoBKMgCFpkgT014/U/8L8w7zbJzPO+3pge+PEuZ+69879nzsyceV48Ho8Y2ipwBZwEmyLp22dQCXpNH1hh8fIOUA+mwVf6CsEPkMffNWASJNm+FswQsVywEdwBZZkQeYS/18EQKOL1GfAINLHzfWAnGAFd4C5I8FkR2AK2gpjin9dyLERGlY6kg4/gFzs6Cz6xfTe4Dw5pnt8cSJ/QI+lbCXPyDUXGKKqO7RLBS2AAHFCiX5BuEnsWE0cit4U5mbDMSfkQT3mXvCOfHxnqcLcryb+OeBQYoUBfuN8epd8LvOu2qUDb4b7F+y8yL03tG0fhA+kHjzM13Drzh9WzbLOynMgiMJ3IKkbBBDVqNm1/47iJyDIuyNky6XvbfCJXgydcGrJlslI8VVaKP0S2gh0OpOB2cEMn8iBodGiu1IJjqshi8DCMpSJku+dXSiLyJljv4Mojc6PTF1lhuGgnNL5ZQ99PA5/O9sguJyJfBxreg2cB3zB4ofH1GfqeG/hearS8BXMi8hwYo/Md60BJ3FHlxhPgPIgrPjlGnNb4ag3vU31S9p0Ch5V+J7ixpOzdskZNBb6kkKWXagUsGsLy6fpN8alVkHpjN2dWMWvCTNsXRng/69Ipk1Jt70Iq6TRsA1mZTj3pGc78hZZkyf+yVFuUImUYL4NryrH2n5rJGUfqu6u87tEs6k6IHGedGeNO4WQkZY89ujxxlkU6kpPJsHaNJRvJkiydeb7biJx0ceJUg0GeY5JZRjS8AjX+aEokm0GbQ4GT/z93gQegVOoGiWSDw3PmgkRTIlnu4B8DKfZbgAEATEjSd2zJTjoAAAAASUVORK5CYII='],
                ['Удобный каталог с фильтрами', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACgAAAAoCAYAAACM/rhtAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA+tpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMDY3IDc5LjE1Nzc0NywgMjAxNS8wMy8zMC0yMzo0MDo0MiAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczpkYz0iaHR0cDovL3B1cmwub3JnL2RjL2VsZW1lbnRzLzEuMS8iIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIiB4bWxuczpzdFJlZj0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL3NUeXBlL1Jlc291cmNlUmVmIyIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ0MgMjAxNSAoV2luZG93cykiIHhtcDpDcmVhdGVEYXRlPSIyMDE2LTExLTA3VDExOjE0OjA5KzAyOjAwIiB4bXA6TW9kaWZ5RGF0ZT0iMjAxNi0xMS0wN1QxMToxNDoxMyswMjowMCIgeG1wOk1ldGFkYXRhRGF0ZT0iMjAxNi0xMS0wN1QxMToxNDoxMyswMjowMCIgZGM6Zm9ybWF0PSJpbWFnZS9wbmciIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6OENDNEVEQzRBNENBMTFFNjk0MzREM0VGRTczOTMxNjAiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6OENDNEVEQzVBNENBMTFFNjk0MzREM0VGRTczOTMxNjAiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDo4Q0M0RURDMkE0Q0ExMUU2OTQzNEQzRUZFNzM5MzE2MCIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo4Q0M0RURDM0E0Q0ExMUU2OTQzNEQzRUZFNzM5MzE2MCIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/Pqq3oFQAAAInSURBVHja7JhLKERRGMfPva63UkrISlEodpKVd94rIQtJrISVlRVJKWVFkRV5ZGUzSikLj7AZ8goJiZUFFogw/odPnU53zMw17r00X/3q3jPT6dd35jvnzKc4nU6G0EAHaAbJIISZF0/gCIyCEfAmfqgRDlDCrIlQkAmGQQGoFSVVypxVcnJU0yoyUbCF2SuaZMFkmwmmyILBNhMMlotEDMVCMZfeoMpsHgHBgKDVoflYXYqnqvMylMASW7XEQeDVy+VQrMhgVGCJ/3AVH4JL+Sem2ShZaf92m1EE3I37yneX1Vi7ZnAAHIMzUGo3wUHQSc+RYO5LUvWyil06VesyQLfO/OEgWxoLI8k8MzPYIwnyUysJPIJysK4j2adaKDcJNkA6uANlYFPeGzUvq9ifZzGXmwJ19L5EHYUDaiAsgiywzJsKZheJLMcjjiTTKJPF1Abhy36vWiwnS6aSZBuXM3Ob4XLTbuS+Ih7Myk6aSXIzoMbD965BA5Pab9z25Rfvg77IFYIdvbP4THjvAtF+FOz3QW7X3WVhXHhvBbcGTwnOqTR/Pc3fCIZ0TqNr2mJ2v7vN8EN6zU8ZWxCeM0AiCU6AdnCiI7fn6br1DIpAL7j6oeC88FxBW8aqMLYlyOV7kvs4DaiJLkYE++wbG4k7oQpXKIO5dBmoBFV0pedy+0b/kzwQP4kYkENVfEFj2+yziz8Gzv35p8lIJIAbOvwdxKWRid4FGAAW1Yl9wL/AZgAAAABJRU5ErkJggg=='],
                ['Только проверенные магазины', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAC0AAAAjCAYAAAAAEIPqAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA+tpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMDY3IDc5LjE1Nzc0NywgMjAxNS8wMy8zMC0yMzo0MDo0MiAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczpkYz0iaHR0cDovL3B1cmwub3JnL2RjL2VsZW1lbnRzLzEuMS8iIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIiB4bWxuczpzdFJlZj0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL3NUeXBlL1Jlc291cmNlUmVmIyIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ0MgMjAxNSAoV2luZG93cykiIHhtcDpDcmVhdGVEYXRlPSIyMDE2LTExLTA3VDExOjAyOjU5KzAyOjAwIiB4bXA6TW9kaWZ5RGF0ZT0iMjAxNi0xMS0wN1QxMTowMzowMSswMjowMCIgeG1wOk1ldGFkYXRhRGF0ZT0iMjAxNi0xMS0wN1QxMTowMzowMSswMjowMCIgZGM6Zm9ybWF0PSJpbWFnZS9wbmciIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6RkM5OTRBQTRBNEM4MTFFNjkwMDRDN0FEQjlCOTlBMTQiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6RkM5OTRBQTVBNEM4MTFFNjkwMDRDN0FEQjlCOTlBMTQiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDpGQzk5NEFBMkE0QzgxMUU2OTAwNEM3QURCOUI5OUExNCIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDpGQzk5NEFBM0E0QzgxMUU2OTAwNEM3QURCOUI5OUExNCIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/Plc2oPwAAAQvSURBVHjavJgJiE1hFMffw1hnbM2QbZoYO2XJTiRLJLKNJVvIVtZsWTORsSShhhRmNGMfxlgilCVLMSlrGYylQRRZYgye/6n/reNz33Lf3Pf+9evd79737jv3u+c7y+fNz8/3hKiyoB3oAdqDZJAEKoNY8BN8A2/BE/AAXCOfPS6qXAjfaQmmglGgToDvlSc1QHMwmOeLwRmwD5wCf0prdJkgxh4H98C8IAYHUgUwFOSC+2B0JGY6BqwBC3lsSv74Oh/mOfgAfvFaddAANAFdQCdQSf1W3sABMIVv74UbRseDI6CXcf4VSOcfFjq4fxwYBKaDnup8H3AbDAdXSuMe1cAlw2CZxZmgIVjv0GDRFz6o3LMruGlM0HnQO1yj5TMHtFbX5IYtwE71+kujG6AbWAl+K3+X/20cjtELjCfOAgPBe4+7ksixFoxVEyFvODNIUPjPaFk8y9W5q2Cimo1I6DBYpMad6d8hGz2BhnuYICJtsKWt4LIazzeuS1xv5s/oAYZbPPdET5uM2U5Q44rgDhhnZ3QbNT7nia4ugBIee41AUMgSYT9YYhodr8ZFUTa62FjseqY/quM0ME0bXawuVvJEX7Hq+LtRy2htB20towvUhQ5RNlgWWlU1fqaOa9kUZJKVvWUY4ixNCrHyc0t6kYmbPFTjdjbfl1qmrxi9V51MZkUXDUlRNVeNM1TZGsea3U4jxWjpAk6ok+vCqQccqiY4pvxZmoct6noKU7ydulmpc55areI7eazOIqF6DHWt1Dkpg9+oDmlxgN8nWUa/oH9ZMVPi40k+fRUXDZaa464VBVRC26XGU+g6fts+XaSc4U2LVbCfz8UxhuNwu6PBXPBZRl44CCYDn1pTG4Pcr8isrI6y9n2tziWCbJDKcUfQKEhVlkBDt4GXbLW6q+tS4a3iJP1U1V4OPwOWuHbh7Sb7wz1G5XWLJeR4lc0KuRa+ci1IzK1rE2O1pGOZwbpCNyCnjTTuTzn+YvJnIzv+YGiMNxrWpiG6iI9bCRvohj5jYeYGCHFaUszl+jN6CJsAXXFVdOjL3/jWLoJDRrazNIydUUKI95S4XmJndDJdw06/GFF2sOtOYqytRhf5wc2aArpOSYD0ncbJcVJ/59l14+KPZxn8zTZJksFq8Eh16Ncdzn5HzlaKw3Ihm7HcdgshnTOtfUhao93gaRjhLob1Qn8wwl8nEkSbwVLdTZlGZzLR3GW0eBDkhvHMnL+5cMXI2gyTLUm45a6E3VmWSwTarDlGQtUHRoVUbneFm4DslGFncLC9vFBVwCSRzITx2CWjZ4P6dhe8DrZ6nagRd5RacXeqNusZq+P/RDcsYhpP8nMf2fHqZ+4ORMpoJ6rCfZc5foqzXdya87npHqWVJKFlfDupNs21bF6ucNun3dI75oFEFm2yJ3KFrvTP3vhfAQYAIDDf5Qj5DH0AAAAASUVORK5CYII='],
//                    ['Полезная рассылка с выгодными предложениями и текущими акциями + купоны на скидку', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACkAAAAiCAYAAADCp/A1AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA+tpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMDY3IDc5LjE1Nzc0NywgMjAxNS8wMy8zMC0yMzo0MDo0MiAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczpkYz0iaHR0cDovL3B1cmwub3JnL2RjL2VsZW1lbnRzLzEuMS8iIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIiB4bWxuczpzdFJlZj0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL3NUeXBlL1Jlc291cmNlUmVmIyIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ0MgMjAxNSAoV2luZG93cykiIHhtcDpDcmVhdGVEYXRlPSIyMDE2LTExLTA3VDExOjAzOjEyKzAyOjAwIiB4bXA6TW9kaWZ5RGF0ZT0iMjAxNi0xMS0wN1QxMTowMzoxNiswMjowMCIgeG1wOk1ldGFkYXRhRGF0ZT0iMjAxNi0xMS0wN1QxMTowMzoxNiswMjowMCIgZGM6Zm9ybWF0PSJpbWFnZS9wbmciIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6MDUwQzhCRENBNEM5MTFFNjhBMzVBRDBBODBGQUZGMDciIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6MDUwQzhCRERBNEM5MTFFNjhBMzVBRDBBODBGQUZGMDciPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDowNTBDOEJEQUE0QzkxMUU2OEEzNUFEMEE4MEZBRkYwNyIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDowNTBDOEJEQkE0QzkxMUU2OEEzNUFEMEE4MEZBRkYwNyIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PvIv8joAAAOsSURBVHjazJhZSBVRGMev5RKFWVpo0gptLxXVi5ZEG+1BEfTYRguVkUWLUhAtD0ZZQbtkaZv0IAXlLbKFerCNbkKLYBtmtthqmZWV9f/gf+BjuDN3vOi9ffDDc2bOHf9zzvmWMxE+n89DGwzWgVEgDjwEB8EB0OgJo7Xi36ngJpgB4kFrMBDsBUUgOtwiO4OjSkg1uK1mbxrYEE6RkWAB6MD+Efb/gGGgBLQFa8BJkK5mP6Qix7AtM5dJgWKlYCPYynFnQO9wzGQEHKeGS14B+lnuywy/BO3Y/ws+h0ibrGCMmUmz1DV+BoqgYjCT/RegZ4hE7gOLjeMYB4myGVym2hXh8u5atjv5uZ8EVql+Y7hEPmO7u5/ZzGXcNDYkXCJ9arnT1L1ZDPLaxMESAzkjGARGg4TmEnmJS14A3vB6Mtilxj1Rbacw1Avc4T6+DF6B9Q7j24A8/s5RpJd7bw4o5/VDoCPbZ+lpxhIdniWxdKi6JllsM5htI/A0mAeuOgmVB/8EP9Q1+dFEtj+CReCduh9v86xU5nuxKlCo7i20ETiB/S5OK2RNcd3ADtVfDl7zRTwBQlWyahdR2C8lwk5gA+uDEjciI7jMcezLg46zXW8Z58/uMSOZl6tWL1QWQOB5N6Wa2Hwwju33YAnbfcF2NU722CQ/zxLnOqxepL16wU3BCtQie4AcdT1deXo+6G/Zk4U2e1P2bxZ4yv18kWGtPFiBWqSEgVi1n06pLJSq6sxbbMssjfTzPKmgsukEEiPHg0f0+qAEapFVllgXrf5poxKWpMbVu3h+jIPAGGa5SLciM1iSmdRnlv4TOMd2LLeFWCW4FoTA6QzyO5lAKhneVroRWcuK3HjnUj7QxM1i9ZsHTJffgxDoZSbLMLUiS8Uc5aiO3n3B4p15rB0/gCnMNDKTA8D9IAV2YBTxMIaWqt9luQ3mK1jYepgWC9X+rFH3ghFokkWUmpTh4DH7Xe1OpVaRX/mmZtlTwJYmFCwFDgLNXjZZSFLvDdBHVf0NbkR6mJ5yVV+K3skuRW7jkcOfQLEvPMubo0uKupftdrm1sOdqf+ZzOQLZXTCWYcZrM2Y1T6B17It3LwP7myqyzrLsEtRP8MuGG6FOgfo3j84JjLtSfOxxm7utdsVSR47gOby5TLbEW3XOD0qk2FrmYWOZXM6wfLCys28M5iY1ynIfs6THkHxmCWTXwW7WiOaY6+VZpiUtTX9mcfvJo0zFtP9quXXFM9fNJm8J+yfAAJ6Y3+cs9RIaAAAAAElFTkSuQmCC']
            ];
        }

        $app->response->setHTML(200, $view);
    }
}