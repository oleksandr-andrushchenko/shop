<?php

namespace SNOWGIRL_SHOP\Item\URI;

use SNOWGIRL_CORE\AbstractApp as App;
use SNOWGIRL_CORE\Http\HttpRequest;
use SNOWGIRL_CORE\Query\Expression;
use SNOWGIRL_CORE\Request;
use SNOWGIRL_CORE\Route;
use SNOWGIRL_CORE\Router;
use SNOWGIRL_CORE\Db\DbInterface;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Http\HttpApp;
use SNOWGIRL_SHOP\Item\SRC;
use SNOWGIRL_SHOP\Item\URI;
use SNOWGIRL_SHOP\Manager\Builder as Managers;
use SNOWGIRL_SHOP\Catalog\URI as CatalogURI;

/**
 * Class Manager
 *
 * @property HttpApp|ConsoleApp app
 * @package SNOWGIRL_SHOP\Item\URI
 */
class Manager
{
    /**
     * @var Managers
     */
    protected $managers;

    /**
     * @var DbInterface
     */
    protected $db;

    /**
     * @var Router
     */
    protected $router;

    public function __construct(App $app)
    {
        $this->managers = $app->managers;
        $this->db = $app->container->db;
        $this->router = $app->router;
    }

    /**
     * @param HttpRequest $request
     * @param bool $domain
     *
     * @return bool|URI
     * @throws \Exception
     */
    public function createFromRequest(HttpRequest $request, $domain = false)
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
     * @param HttpRequest $request
     *
     * @return array|bool
     * @throws \Exception
     */
    protected function parseRequestPath(HttpRequest $request)
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

//            throw new NotFoundHttpException;
            return false;
        }

        return $output;
    }

    /**
     * @param $path
     * @param HttpRequest $request
     *
     * @return bool
     * @throws \SNOWGIRL_CORE\Exception
     */
    protected function checkRedirectWithBestMatch($path, HttpRequest $request)
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
                ->setOrders(new Expression('LENGTH(' . $this->db->quote('uri') . ') DESC'))
                ->getObject();

            if ($brand) {
                $params['brand_id'] = $brand->getId();
            }

            $request->redirect(new \SNOWGIRL_SHOP\Catalog\URI($params), 301);
            return true;
        }

        return false;
    }

    protected function checkRedirectWithItemTable($id, HttpRequest $request)
    {
        if ($id = $this->managers->itemRedirects->getByIdFrom($id)) {
            if (!$item = $this->managers->items->find($id)) {
                $item = $this->managers->archiveItems->find($id);
            }

            if ($item) {
                $request->redirect($this->managers->items->getLink($item), 301);

                return true;
            }
        }

        return false;
    }

    protected function checkRedirectWithTable($path, HttpRequest $request)
    {
        if ($uri = $this->managers->redirects->getByUriFrom($path)) {
            $request->redirectToRoute('default', ['action' => $uri], 301);
            return true;
        }

        return false;
    }

    protected function checkRedirectWithLessRequirements($path, HttpRequest $request)
    {
        false && $path;

        if (!$request->getDevice()->isRobot()) {
            $request->redirectToRoute('default', [], 301);
            return true;
        }

        return false;
    }

    protected function checkRedirectIndex($path, HttpRequest $request)
    {
        false && $path;
        $request->redirectToRoute('default', [], 301);
        return true;
    }
}