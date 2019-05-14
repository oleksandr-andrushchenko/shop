<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 7/23/17
 * Time: 7:57 PM
 */

namespace SNOWGIRL_SHOP\View\Layout;

use SNOWGIRL_CORE\Script\Css;
use SNOWGIRL_CORE\Script\Js;

/**
 * @todo set active menu item on default actions (e.g. poyasa-remni-i-podtyazhki = catalog, poyasa = catalog etc...)
 * Class OpenDoor
 * @package SNOWGIRL_SHOP\View\Layout
 */
class OpenDoor extends \SNOWGIRL_CORE\View\Layout\OpenDoor
{
    protected $headerSearch = true;
    protected $sign = 'Лучший женский интернет-каталог';

    /**
     * Nice digital fonts: https://fonts.google.com/?selection.family=Acme|Cambo|Cardo|Catamaran|Cherry+Cream+Soda|Chonburi|Cinzel|David+Libre|Delius+Swash+Caps|Gilda+Display|Hammersmith+One|Inder|Martel|Martel+Sans|Merriweather|Montserrat|Montserrat+Alternates|Noto+Serif+KR|Quicksand|Rubik|Ruslan+Display|Sriracha|Suez+One|Syncopate|Vampiro+One|Yatra+One
     * @return \SNOWGIRL_CORE\View\Layout
     */
    protected function addCssNodes()
    {
        return parent::addCssNodes()
            ->addHeadCss(new Css('@snowgirl-shop/core.css'))
            ->addLazyCss(new Css('@snowgirl-core/rating.css'))
            ->addLazyCss(new Css('https://fonts.googleapis.com/css?family=Chonburi'))
            ->addLazyCss(new Css('.price .val,.old-price .val{font-family: \'Chonburi\', cursive;}', true));
    }

    protected function addJsNodes()
    {
        return $this->addJs(new Js('//ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js'))
            ->addJs(new Js('@snowgirl-core/core.js'))
            ->addJs(new Js('@snowgirl-shop/core.js'));
    }

    protected function makeHeader()
    {
        return $this->stringifyContent('@snowgirl-core/layout/header.phtml');
    }

    protected function makeBreadcrumbs()
    {
        return $this->stringifyContent('@snowgirl-core/layout/breadcrumbs.phtml');
    }

    protected function makeContent()
    {
        return $this->stringifyContent('@snowgirl-shop/layout/content.phtml');
    }

    protected function makeFooter()
    {
        return $this->stringifyContent('@snowgirl-core/layout/footer.phtml');
    }
}