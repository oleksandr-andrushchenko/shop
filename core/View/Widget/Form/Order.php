<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/17/18
 * Time: 10:58 PM
 */

namespace SNOWGIRL_SHOP\View\Widget\Form;

use SNOWGIRL_CORE\View\Widget\Form\Contact;

/**
 * Class Order
 * @package SNOWGIRL_SHOP\View\Widget\Form
 */
class Order extends Contact
{
//    protected $captcha = false;

    protected $classColOffset = 'col-sm-offset-4 col-sm-8';
    protected $classColLabel = 'col-sm-4';
    protected $classColInput = 'col-sm-8';

    protected function addTexts()
    {
        return parent::addTexts()
            ->addText('widget.form.order');
    }
}