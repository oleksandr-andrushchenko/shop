<?php

namespace SNOWGIRL_SHOP\View\Layout;

use SNOWGIRL_CORE\Script\Css;
use SNOWGIRL_CORE\Script\Js;

/**
 * @todo    set active menu item on default actions (e.g. poyasa-remni-i-podtyazhki = catalog, poyasa = catalog etc...)
 * Class OpenDoor
 * @package SNOWGIRL_SHOP\View\Layout
 */
class Outer extends \SNOWGIRL_CORE\View\Layout\Outer
{
    protected $headerSearch = true;
    protected $sign = 'Лучший женский интернет-каталог';

    /**
     * Nice digital fonts:
     * https://fonts.google.com/?selection.family=Acme|Cambo|Cardo|Catamaran|Cherry+Cream+Soda|Chonburi|Cinzel|David+Libre|Delius+Swash+Caps|Gilda+Display|Hammersmith+One|Inder|Martel|Martel+Sans|Merriweather|Montserrat|Montserrat+Alternates|Noto+Serif+KR|Quicksand|Rubik|Ruslan+Display|Sriracha|Suez+One|Syncopate|Vampiro+One|Yatra+One
     *
     * @return \SNOWGIRL_CORE\View\Layout
     */
    protected function addCssNodes()
    {
        return parent::addCssNodes()
            ->addHeadCss(new Css('@shop/core.css'))
            ->addLazyCss(new Css('@core/rating.css'))
            ->addLazyCss(new Css('https://fonts.googleapis.com/css?family=Chonburi&display=swap'))
            ->addLazyCss(new Css('.price .val,.old-price .val{font-family: \'Chonburi\', cursive;}', true));
    }

    protected function addJsNodes()
    {
        return $this->addJs(new Js('//ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js'))
            ->addJs(new Js('@core/core.js'))
            ->addJs(new Js('@shop/core.js'));
    }

    protected function makeHeader()
    {
        return $this->stringifyContent('@core/layout/header.phtml');
    }

    protected function makeBreadcrumbs()
    {
        return $this->stringifyContent('@core/layout/breadcrumbs.phtml');
    }

    protected function makeContent()
    {
        return $this->stringifyContent('@shop/layout/content.phtml');
    }

    protected function makeFooter()
    {
        return $this->stringifyContent('@core/layout/footer.phtml');
    }
}