<?php

namespace SNOWGIRL_SHOP\View\Widget\Form;

use SNOWGIRL_CORE\View\Widget;
use SNOWGIRL_CORE\View\Widget\Form\Contact;

class Order extends Contact
{
//    protected $captcha = false;

    protected $classColOffset = 'col-sm-offset-4 col-sm-8';
    protected $classColLabel = 'col-sm-4';
    protected $classColInput = 'col-sm-8';

    protected function addTexts(): Widget
    {
        return parent::addTexts()->addText('widget.form.order');
    }
}