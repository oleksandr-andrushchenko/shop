<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 5/15/19
 * Time: 12:18 AM
 */

namespace SNOWGIRL_SHOP\Controller\Outer;

use SNOWGIRL_CORE\Controller\Outer\PrepareServicesTrait;
use SNOWGIRL_CORE\Controller\Outer\ProcessTypicalPageTrait;
use SNOWGIRL_SHOP\App\Web as App;

class TranslateAction
{
    use PrepareServicesTrait;
    use ProcessTypicalPageTrait;

    /**
     * @param App $app
     */
    public function __invoke(App $app)
    {
        $this->prepareServices($app);

        $text = $app->request->get('text');
        $source = $app->request->get('source');
        $target = $app->request->get('target');

        if ($tmp = $app->utils->catalog->translateRaw($text, $source, $target)) {
            if (is_array($tmp) && isset($tmp['text'])) {
                $view = $tmp['text'];
            } else {
                $view = null;
            }
        } else {
            $view = null;
        }

        $app->response->setHTML(200, $view);
    }
}