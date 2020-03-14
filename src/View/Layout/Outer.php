<?php

namespace SNOWGIRL_SHOP\View\Layout;

use SNOWGIRL_CORE\View\Layout;

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
    protected function addCssNodes(): Layout
    {
        return parent::addCssNodes()
            ->addHeadCss('@shop/core.css')
            ->addLazyCss('@core/rating.css')
            ->addLazyCss('https://fonts.googleapis.com/css?family=Chonburi&display=swap')
            ->addLazyCss('.price .val,.old-price .val{font-family: \'Chonburi\', cursive;}', true);
    }

    protected function addJsNodes(): Layout
    {
        return $this->addJs('//ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js')
            ->addJs('@core/core.js')
            ->addJs('@shop/core.js');
    }

    protected function makeHeader(): string
    {
        return $this->stringifyContent('@core/layout/header.phtml');
    }

    protected function makeBreadcrumbs(): string
    {
        return $this->stringifyContent('@core/layout/breadcrumbs.phtml');
    }

    protected function makeContent(): string
    {
        return $this->stringifyContent('@shop/layout/content.phtml');
    }

    protected function makeFooter(): string
    {
        return $this->stringifyContent('@core/layout/footer.phtml');
    }
}