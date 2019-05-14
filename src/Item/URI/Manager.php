<?php
/**
 * Created by PhpStorm.
 * User: snowgirl
 * Date: 1/28/18
 * Time: 3:12 AM
 */

namespace SNOWGIRL_SHOP\Item\URI;

use SNOWGIRL_CORE\App;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_CORE\Request;
use SNOWGIRL_CORE\Service\Rdbms as DB;
use SNOWGIRL_SHOP\App\Console;
use SNOWGIRL_SHOP\App\Web;
use SNOWGIRL_SHOP\Item\SRC;
use SNOWGIRL_SHOP\Item\URI;
use SNOWGIRL_SHOP\Manager\Builder as Managers;
use SNOWGIRL_SHOP\Catalog\URI as CatalogURI;

/**
 * Class Manager
 * @property App|Web|Console
 * @package SNOWGIRL_SHOP\Item\URI
 */
class Manager
{
    /** @var Managers */
    protected $managers;

    /** @var DB */
    protected $db;

    public function __construct(App $app)
    {
        $this->managers = $app->managers;
        $this->db = $app->services->rdbms;
    }

    /**
     * @param Request $request
     * @param bool $domain
     * @return bool|URI
     * @throws \Exception
     */
    public function createFromRequest(Request $request, $domain = false)
    {
        $params = $this->parseRequestPath($request);

        if (is_array($params)) {
            return new URI(array_merge_recursive(
                $request->getParams(),
                $params
            ), $domain);
        }

        return false;
    }

    /**
     * @param Request $request
     * @return array|bool
     * @throws \Exception
     */
    protected function parseRequestPath(Request $request)
    {
        $output = [];

        $rawUri = trim($request->getPathInfo(), '/');

        if (CatalogURI::addUriPrefix()) {
            $rawUri = substr($rawUri, 7);
            $rawUri = ltrim($rawUri, '/');
        }

        $tmp = explode('-', $rawUri);
        $id = array_pop($tmp);

        if (is_numeric($id)) {
            $output[URI::ID] = $id;
        }

        if (!isset($output[URI::ID]) || !SRC::checkId($output[URI::ID], $this->managers)) {
            if (isset($output[URI::ID])) {
                $this->checkRedirectWithItemTable($output[URI::ID], $request);
            }

            $this->checkRedirectWithTable($rawUri, $request);
            $this->checkRedirectWithBestMatch($rawUri, $request);

//            $this->checkRedirectWithLessRequirements($rawUri, $request);
            //@todo log 404 separately...
//            $this->checkRedirectIndex($rawUri, $request);

//            throw new NotFound;
            return false;
        }

        return $output;
    }

    /**
     * @param $path
     * @param Request $request
     * @return bool
     * @throws \Exception
     */
    protected function checkRedirectWithBestMatch($path, Request $request)
    {
        $params = [];

        $categories = $this->managers->categories->getLeafObjects();
        $this->managers->categories->sortByUriLength($categories);

        foreach ($categories as $category) {
            if (false !== strpos($path, $category->getUri())) {
                $params['category_id'] = $category->getId();
                break;
            }
        }

        if (isset($params['category_id'])) {
            $tmp = explode('-', $path);

            $brand = $this->managers->brands->clear()
                ->setWhere(['uri' => $tmp])
                ->setOrders(new Expr('LENGTH(' . $this->db->quote('uri') . ') DESC'))
                ->getObject();

            if ($brand) {
                $params['brand_id'] = $brand->getId();
            }

            $request->redirect(new \SNOWGIRL_SHOP\Catalog\URI($params), 301);
            return true;
        }

        return false;
    }

    protected function checkRedirectWithItemTable($id, Request $request)
    {
        if ($id = $this->managers->itemRedirects->getByIdFrom($id)) {
            $item = $this->managers->items->find($id);
            $request->redirect($this->managers->items->getLink($item), 301);
            return true;
        }

        return false;
    }

    protected function checkRedirectWithTable($path, Request $request)
    {
        if ($uri = $this->managers->redirects->getByUriFrom($path)) {
            $request->redirectToRoute('default', ['action' => $uri], 301);
            return true;
        }

        return false;
    }

    protected function checkRedirectWithLessRequirements($path, Request $request)
    {
        false && $path;

        if (!$request->getDevice()->isRobot()) {
            $request->redirectToRoute('default', [], 301);
            return true;
        }

        return false;
    }

    protected function checkRedirectIndex($path, Request $request)
    {
        false && $path;
        $request->redirectToRoute('default', [], 301);
        return true;
    }
}