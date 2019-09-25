<?php

namespace SNOWGIRL_SHOP\Catalog\URI;

use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_CORE\Request;
use SNOWGIRL_CORE\Service\Logger;
use SNOWGIRL_CORE\Service\Rdbms as DB;
use SNOWGIRL_CORE\Service\Storage\Query;
use SNOWGIRL_CORE\App;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Manager\Builder as Managers;
use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalog;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Entity\Redirect;

class Manager
{
    /** @var Managers */
    protected $managers;
    /** @var DB */
    protected $db;
    /** @var Logger */
    protected $logger;

    public function __construct(App $app)
    {
        $this->managers = $app->managers;
        $this->db = $app->managers->catalog->getStorage();
        $this->logger = $app->services->logger;
    }

    /**
     * @param Request $request
     * @param bool    $domain
     *
     * @return bool|URI
     * @throws \Exception
     */
    public function createFromRequest(Request $request, $domain = false)
    {
        $params = $this->parseRequestPath($request, $page);

        if (is_array($params)) {
            $uri = new URI(array_merge_recursive($request->getParams(), $params), $domain);

            if ($page) {
//                $uri->getSEO()->setAliases($page->getMetaKey('aliases', []));
//                $uri->getSRC()->setCount($page->getMetaKey('count'));
//                $uri->setAliases($page->getMetaKey('aliases', []));

                $uri->getSRC()->setCatalogPage($page);
            }

            return $uri;
        }

        return false;
    }

    protected function getComponentsTableToPk()
    {
        $components = $this->managers->catalog->getComponentsOrderByRdbmsKey();

        return Arrays::mapByKeyValueMaker($components, function ($k, $entity) {
            false && $k;
            /** @var Entity $entity */
            return [$entity::getTable(), $entity::getPk()];
        });
    }

    /**
     * Returns all possible found params from $path
     *
     * @todo optimize loop... then remove URI's cache prefetch
     *
     * @param Request          $request
     * @param PageCatalog|null $page
     *
     * @return array|bool
     * @throws \Exception
     */
    protected function parseRequestPath(Request $request, PageCatalog &$page = null)
    {
        $output = [];

        $rawUri = trim($request->getPathInfo(), '/');

        if (URI::CATALOG == substr($rawUri, 0, 7)) {
            $rawUri = substr($rawUri, 7);
            $rawUri = ltrim($rawUri, '/');
        }

        if ('' == $rawUri) {
            return $output;
        }

        $rawUri = urldecode($rawUri);

        $rawUriArray = explode('/', $rawUri);

        foreach (array_intersect($rawUriArray, URI::NON_ATTR_PATH_PARAMS) as $param) {
            $output[$param] = 1;
        }

        if (!$attrUriArray = array_diff($rawUriArray, URI::NON_ATTR_PATH_PARAMS)) {
            $page = $this->managers->catalog->clear()->getObjectByUri($rawUri);
            return $output;
        }

        if (isset($page)) {
            if ($page) {
                return $page->getParams(true);
            }
        } elseif ($page = $this->managers->catalog->clear()->getObjectByUri($rawUri)) {
            return $page->getParams(true);
        }

        $output += $this->getUriAttrParamsByComponentsTables($attrUriArray, $unknownUriArray, true);

        if (count($output) != count($rawUriArray)) {
            $this->checkRedirectWithSeoUriFix($rawUriArray, $request);
            $this->checkRedirectWithDuplicates($rawUriArray, $request);
//            $this->checkRedirectWithOldFormat($uri, $rawUri, $request);
            $this->checkRedirectWithTable($rawUriArray, $request);
//            $this->checkRedirectWithCatalogHistory($rawUri, $request);

            if (!$isCan = $this->checkRedirectWithPartials($unknownUriArray, $output, true, $request)) {
                $this->checkRedirectWithPageComponents($output, $request);
            }

//            $this->checkRedirectWithPartials($unknownUriArray, $output, false, $request);
//            $this->checkRedirectWithLessRequirements($output, $request);
            //@todo log 404 separately...
//            $this->checkRedirectIndex($page, $request);

//            throw new NotFound;
            return false;
        }

        return $output;
    }

    /**
     * @todo cache & optimize...
     * @todo add name_hash columns for index build???!?
     *
     * @param array      $uri - attrs uri array
     * @param null       $unknown
     * @param bool|false $activeOnly
     *
     * @return array
     * @throws \Exception
     */
    protected function getUriAttrParamsByComponentsTables(array $uri, &$unknown = null, $activeOnly = false)
    {
        $output = [];

        $componentsTableToPk = $this->getComponentsTableToPk();
        $table = array_intersect(URI::getPagePath([], true), array_keys($componentsTableToPk));

        $req = new Query(['params' => []]);
        $req->text = 'SELECT ' . implode(', ', [
                'GROUP_CONCAT(' . $this->db->quote('table') . ') AS ' . $this->db->quote('table'),
                $this->db->quote('uri'),
                $this->db->quote('id'),
//                'COUNT(*) AS ' . $this->db->quote('cnt')
            ]) . ' FROM (' .
            implode(' UNION ', array_map(function ($table) use ($uri, $componentsTableToPk, $activeOnly, $req) {
                $where = ['uri' => $uri];

//                if ($activeOnly && $this->managers->getByTable($table)->getEntity()->hasAttr('is_active')) {
//                    $where['is_active'] = 1;
//                }

                //@todo 404 instead of is_active
//                $where['is_404'] = 0;

                return implode(' ', [
                    $this->db->makeSelectSQL(new Expr(implode(', ', [
                        '\'' . $table . '\' AS ' . $this->db->quote('table'),
                        $this->db->quote('uri'),
                        $this->db->quote($componentsTableToPk[$table]) . ' AS ' . $this->db->quote('id')
                    ])), false, $req->params),
                    $this->db->makeFromSQL($table),
                    $this->db->makeWhereSQL($where, $req->params)
                ]);
            }, $table)) . ') AS ' . $this->db->quote('t') . ' GROUP BY ' . $this->db->quote('uri');

        $req = $this->db->req($req)->reqToArrays();

        $known = [];

        foreach ($req as $item) {
            $tables = explode(',', $item['table']);

            if (1 < count($tables)) {
                $this->logger->make(implode(' ', [
                    'Cross-table "' . $item['uri'] . '" uri',
                    'duplicates found in',
                    '"' . implode('", "', $tables) . '"'
                ]), Logger::TYPE_ERROR);
            }

            $output[$componentsTableToPk[$tables[0]]] = $item['id'];
            $known[] = $item['uri'];
        }

        $unknown = array_diff($uri, $known);

        return $output;
    }

    /**
     * @param         $uri
     * @param         $rawUri
     * @param Request $request
     *
     * @return bool
     */
    protected function checkRedirectWithOldFormat($uri, $rawUri, Request $request)
    {
        foreach (['category', 'brand', 'color'] as $k) {
            if ($k . '/' . $uri == $rawUri) {
                $request->redirectToRoute('default', ['action' => $uri], 301);
                return true;
            }
        }

        return false;
    }

    /**
     * @todo ...
     * @todo use ftdbms...
     * @todo add rdbms as alternative...
     *
     * @param         $rawUri
     * @param Request $request
     *
     * @throws \Exception
     */
    protected function checkRedirectWithCatalogHistory($rawUri, Request $request)
    {
        /** @var PageCatalog $page */
        $page = $this->managers->catalog
            ->setWhere(new Expr(implode(' ', [
                $this->db->quote('uri_history') . ' IS NOT NULL',
                'AND',
                'FIND_IN_SET(?, ' . $this->db->quote('uri_history') . ')'
            ]), $rawUri))
            ->getObject();

        if ($page) {
            $tmp = $this->managers->catalog->getCatalogUri($page);

            //@todo....
//            foreach ($this->normalizedParams as $k => $v) {
//                $tmp->set($k, $v);
//            }

            $request->redirect((string)$tmp, 301);
        }
    }

    /**
     * @param array   $rawUri
     * @param Request $request
     *
     * @return bool
     */
    protected function checkRedirectWithSeoUriFix(array $rawUri, Request $request)
    {
        $tmp = array_map(function ($slug) {
            return Entity::normalizeUri($slug);
        }, $rawUri);

        if ($rawUri != $tmp) {
            $request->redirectToRoute('default', ['action' => implode('/', $tmp)], 301);
            return true;
        }

        return false;
    }

    /**
     * @todo ...
     * @todo fix... (check live logs...)
     *
     * @param array   $rawUri
     * @param Request $request
     *
     * @return bool
     */
    protected function checkRedirectWithTable(array $rawUri, Request $request)
    {
        $nonComponents = $rawUri;

        $map = $this->managers->redirects->getByUriFrom($nonComponents);

        if (count($map) > 0) {
            foreach ($nonComponents as $k => $item) {
                if (isset($map[$item])) {
                    $nonComponents[$k] = $map[$item];
                }
            }

            $queries = [];

            foreach ($nonComponents as $i => $nonComponent) {
                if (false !== strpos($nonComponent, '&')) {
                    $queries[] = $nonComponent;
                    unset($nonComponents[$i]);
                }
            }

            $params = ['action' => implode('/', $nonComponents)];

            if ($queries) {
                foreach ($queries as $query) {
                    $query = htmlspecialchars_decode($query);

                    foreach (explode('&', $query) as $kv) {
                        list($k, $v) = explode('=', $kv);
                        $params[$k] = $v;
                    }
                }
            }

            $request->redirectToRoute('default', $params, 301);
            return true;
        }

        return false;
    }

    /**
     * @param array   $params
     * @param Request $request
     *
     * @return bool
     * @throws \Exception
     */
    protected function checkRedirectWithPageComponents(array $params, Request $request)
    {
        if ($params && $page = $this->managers->catalog->clear()->findByParams($params)) {
            $request->redirect($this->managers->catalog->getLink($page), 301);
            return true;
        }

        return false;
    }

    protected $checkRedirectWithPartialsParamsCache;

    /**
     * @param array   $unknownUriArray
     * @param array   $params
     * @param         $sayIfCanOnly
     * @param Request $request
     *
     * @return bool
     * @throws \Exception
     */
    protected function checkRedirectWithPartials(array $unknownUriArray, array $params, $sayIfCanOnly, Request $request)
    {
        $rawParams = $params;

        if (null === $this->checkRedirectWithPartialsParamsCache) {
            if (!isset($params['category_id']) && $unknownUriArray) {
                foreach ($this->managers->categories->findAll() as $categoryId => $category) {
                    $categoryUri = $category->getUri();

                    foreach ($unknownUriArray as $unknownKey => $unknownUri) {
                        //check if old category has new category name
                        if (false !== strpos($unknownUri, $categoryUri)) {
                            $params['category_id'] = $categoryId;

                            //check if old category has tags names
                            if ($possibleTagsUris = array_diff(explode('-', $unknownUri), [$categoryUri])) {
                                if ($tagsId = $this->managers->tags->getColumnToId('uri', ['uri' => array_values($possibleTagsUris)])) {
                                    if (isset($params['tag_id'])) {
                                        if (!is_array($params['tag_id'])) {
                                            $params['tag_id'] = [$params['tag_id']];
                                        }
                                    } else {
                                        $params['tag_id'] = [];
                                    }

                                    $params['tag_id'] = array_merge($params['tag_id'], $tagsId);
                                }
                            }
                            unset($unknownUriArray[$unknownKey]);
                            break 2;
                        }
                    }
                }
            }

            $this->checkRedirectWithPartialsParamsCache = $params;
        }

        $isCan = count($rawParams) < count($this->checkRedirectWithPartialsParamsCache);

        if ($sayIfCanOnly) {
            return $isCan;
        }

        if ($isCan) {
            $request->redirect(new URI($this->checkRedirectWithPartialsParamsCache), 301);
            return true;
        }

        return false;
    }

    protected function checkRedirectWithLessRequirements(array $params, Request $request)
    {
//        if (!$request->getDevice()->isRobot()) {
        $request->redirect(new URI($params), 301);
        return true;
//        }

//        return false;
    }

    /**
     * @param array   $rawUri
     * @param Request $request
     *
     * @return bool
     */
    protected function checkRedirectWithDuplicates(array $rawUri, Request $request)
    {
        $tmp = array_unique($rawUri);

        if ($rawUri != $tmp) {
            $request->redirectToRoute('default', ['action' => implode('/', $tmp)], 301);
            return true;
        }

        return false;
    }

    /**
     * @todo remove...
     *
     * @param URI    $uri
     * @param string $domain
     *
     * @return mixed|string
     */
    public function getRequestUri(URI $uri, $domain = 'master')
    {
        $output = $uri->output(false);

        if (($tmp = parse_url($output)) && isset($tmp['path'])) {
            $output = $tmp['path'];

            if (isset($tmp['query']) && $tmp['query']) {
                $output .= '?' . $tmp['query'];
            }
        } else {
            $output = str_replace($domain, '', $output);
        }

        return $output;
    }

    /**
     * @param URI $from
     * @param URI $to
     *
     * @return int
     */
    public function addRedirect(URI $from, URI $to)
    {
        $tmp = [
            'uri_from' => $this->getRequestUri($from),
            'uri_to' => $this->getRequestUri($to)
        ];

        foreach ($tmp as $column => $value) {
            $tmp[$column] = trim(ltrim(str_replace(URI::CATALOG, '', trim($value, '/')), '?'), '/');
        }

        $redirect = new Redirect($tmp);

        return $this->managers->redirects->insertOne($redirect, true);
    }

    /**
     * Hard-weight operation...
     *
     * @param URI      $uri
     * @param \Closure $itemMapper
     *
     * @return URI[]
     */
    public function getOtherVariants(URI $uri, \Closure $itemMapper)
    {
        $output = [];

        /** @var PageCatalog $searchPage */
//        $searchPage = null;

//        if ($query = $uri->get(URI::QUERY)) {
//            while ($query) {
//                $query = mb_substr($query, 0, mb_strlen($query) - 1);
//
//                if ($page = $this->managers->catalog->getObjectByQuery($query)) {
//                    $searchPage = $page;
//                    break;
//                }
//            }
//        }

//        if ($searchPage) {
//            $params = $this->managers->catalog->getUriParams($searchPage);
//        } else {
        $params = $uri->getParams();
//        }

        $combinations = Arrays::getUniqueCombinations(array_keys($params));
        $paramsCount = count($params);
        $combinations = array_filter($combinations, function ($combination) use ($paramsCount) {
            return count($combination) < $paramsCount;
        });

        $combinations = array_reverse($combinations);

        foreach ($combinations as $combination) {
            $tmpParams = [];

            foreach ($combination as $param) {
                $tmpParams[$param] = $params[$param];
            }

            $tmp = new URI($tmpParams);

            if ($tmp->getSRC()->getTotalCount()) {
                $output[] = $tmp;
            }
        }

        foreach ($output as $k => $uri) {
            $output[$k] = $itemMapper($uri);
        }

        return $output;
    }

    protected function checkRedirectIndex($path, Request $request)
    {
        false && $path;
        $request->redirectToRoute('default', [], 301);
        return true;
    }
}