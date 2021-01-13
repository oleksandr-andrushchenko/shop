<?php

namespace SNOWGIRL_SHOP\Catalog\URI;

use Psr\Log\LoggerInterface;
use SNOWGIRL_CORE\AbstractApp as App;
use SNOWGIRL_CORE\Entity;
use SNOWGIRL_CORE\Http\HttpRequest;
use SNOWGIRL_CORE\Mysql\MysqlInterface;
use SNOWGIRL_CORE\Mysql\MysqlQueryExpression;
use SNOWGIRL_CORE\Mysql\MysqlQuery;
use SNOWGIRL_SHOP\Catalog\URI;
use SNOWGIRL_SHOP\Console\ConsoleApp;
use SNOWGIRL_SHOP\Http\HttpApp;
use SNOWGIRL_SHOP\Manager\Builder as Managers;
use SNOWGIRL_SHOP\Entity\Page\Catalog as PageCatalog;
use SNOWGIRL_CORE\Helper\Arrays;
use SNOWGIRL_CORE\Entity\Redirect;
use Throwable;

class Manager
{
    /**
     * @var Managers
     */
    private $managers;

    /**
     * @var MysqlInterface
     */
    private $mysql;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $checkRedirectWithPartialsParamsCache;

    /**
     * Manager constructor.
     *
     * @param App|HttpApp|ConsoleApp $app
     */
    public function __construct(App $app)
    {
        $this->managers = $app->managers;
        $this->mysql = $app->managers->catalog->getMysql();
        $this->logger = $app->container->logger;
    }

    /**
     * @param HttpRequest $request
     * @param bool $domain
     *
     * @return bool|URI
     * @throws Throwable
     * @throws \SNOWGIRL_CORE\Exception
     */
    public function createFromRequest(HttpRequest $request, $domain = false)
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

    /**
     * @todo remove...
     *
     * @param URI $uri
     * @param string $domain
     *
     * @return mixed|string
     * @throws Throwable
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
     * @return bool
     * @throws Throwable
     */
    public function addRedirect(URI $from, URI $to): bool
    {
        $tmp = [
            'uri_from' => $this->getRequestUri($from),
            'uri_to' => $this->getRequestUri($to)
        ];

        foreach ($tmp as $column => $value) {
            $tmp[$column] = trim(ltrim(str_replace(URI::CATALOG, '', trim($value, '/')), '?'), '/');
        }

        $redirect = new Redirect($tmp);

        return $this->managers->redirects->insertOne($redirect, ['ignore' => true]);
    }

    /**
     * Hard-weight operation...
     *
     * @param URI $uri
     * @param callable $itemMapper
     *
     * @return URI[]
     */
    public function getOtherVariants(URI $uri, callable $itemMapper)
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

    private function getComponentsTableToPk()
    {
        $components = $this->managers->catalog->getComponentsOrderByDbKey();

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
     * @param HttpRequest $request
     * @param PageCatalog|null $page
     *
     * @return array|bool|mixed|null
     * @throws Throwable
     * @throws \SNOWGIRL_CORE\Exception
     */
    private function parseRequestPath(HttpRequest $request, PageCatalog &$page = null)
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

//            throw new NotFoundHttpException;
            return false;
        }

        return $output;
    }

    /**
     * @todo cache & optimize...
     * @todo add name_hash columns for index build???!?
     *
     * @param array $uri - attrs uri array
     * @param null $unknown
     * @param bool|false $activeOnly
     *
     * @return array
     * @throws \Exception
     */
    private function getUriAttrParamsByComponentsTables(array $uri, &$unknown = null, bool $activeOnly = false)
    {
        $output = [];

        $componentsTableToPk = $this->getComponentsTableToPk();
        $table = array_intersect(URI::getPagePath([], true), array_keys($componentsTableToPk));

        $req = new MysqlQuery(['params' => []]);
        $req->text = 'SELECT ' . implode(', ', [
                'GROUP_CONCAT(' . $this->mysql->quote('table') . ') AS ' . $this->mysql->quote('table'),
                $this->mysql->quote('uri'),
                $this->mysql->quote('id'),
//                'COUNT(*) AS ' . $this->mysql->quote('cnt')
            ]) . ' FROM (' .
            implode(' UNION ', array_map(function ($table) use ($uri, $componentsTableToPk, $activeOnly, $req) {
                $where = ['uri' => $uri];

//                if ($activeOnly && $this->managers->getByTable($table)->getEntity()->hasAttr('is_active')) {
//                    $where['is_active'] = 1;
//                }

                //@todo 404 instead of is_active
//                $where['is_404'] = 0;

                return implode(' ', [
                    $this->mysql->makeSelectSQL(new MysqlQueryExpression(implode(', ', [
                        '\'' . $table . '\' AS ' . $this->mysql->quote('table'),
                        $this->mysql->quote('uri'),
                        $this->mysql->quote($componentsTableToPk[$table]) . ' AS ' . $this->mysql->quote('id')
                    ])), false, $req->params),
                    $this->mysql->makeFromSQL($table),
                    $this->mysql->makeWhereSQL($where, $req->params, null, $req->placeholders)
                ]);
            }, $table)) . ') AS ' . $this->mysql->quote('t') . ' GROUP BY ' . $this->mysql->quote('uri');

        $req = $this->mysql->reqToArrays($req);

        $known = [];

        foreach ($req as $item) {
            $tables = explode(',', $item['table']);

            if (1 < count($tables)) {
                $this->logger->warning(implode(' ', [
                    'Cross-table "' . $item['uri'] . '" uri',
                    'duplicates found in',
                    '"' . implode('", "', $tables) . '"'
                ]));
            }

            $output[$componentsTableToPk[$tables[0]]] = $item['id'];
            $known[] = $item['uri'];
        }

        $unknown = array_diff($uri, $known);

        return $output;
    }

    /**
     * @param $uri
     * @param $rawUri
     * @param HttpRequest $request
     *
     * @return bool
     * @throws Throwable
     */
    private function checkRedirectWithOldFormat($uri, $rawUri, HttpRequest $request): bool
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
     * @param $rawUri
     * @param HttpRequest $request
     *
     * @return bool
     * @throws Throwable
     */
    private function checkRedirectWithCatalogHistory($rawUri, HttpRequest $request): bool
    {
        /** @var PageCatalog $page */
        $page = $this->managers->catalog
            ->setWhere(new MysqlQueryExpression(implode(' ', [
                $this->mysql->quote('uri_history') . ' IS NOT NULL',
                'AND',
                'FIND_IN_SET(?, ' . $this->mysql->quote('uri_history') . ')'
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
     * @param array $rawUri
     * @param HttpRequest $request
     *
     * @return bool
     * @throws Throwable
     */
    private function checkRedirectWithSeoUriFix(array $rawUri, HttpRequest $request): bool
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
     * @param array $rawUri
     * @param HttpRequest $request
     *
     * @return bool
     * @throws Throwable
     */
    private function checkRedirectWithTable(array $rawUri, HttpRequest $request): bool
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
                        [$k, $v] = explode('=', $kv);
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
     * @param array $params
     * @param HttpRequest $request
     *
     * @return bool
     * @throws Throwable
     */
    private function checkRedirectWithPageComponents(array $params, HttpRequest $request): bool
    {
        if ($params && $page = $this->managers->catalog->clear()->findByParams($params)) {
            $request->redirect($this->managers->catalog->getLink($page), 301);
            return true;
        }

        return false;
    }

    /**
     * @param array $unknownUriArray
     * @param array $params
     * @param bool $sayIfCanOnly
     * @param HttpRequest $request
     * @return bool
     * @throws \SNOWGIRL_CORE\Exception
     */
    private function checkRedirectWithPartials(array $unknownUriArray, array $params, bool $sayIfCanOnly, HttpRequest $request): bool
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

    /**
     * @param array $params
     * @param HttpRequest $request
     *
     * @return bool
     * @throws \SNOWGIRL_CORE\Exception
     */
    private function checkRedirectWithLessRequirements(array $params, HttpRequest $request): bool
    {
//        if (!$request->getDevice()->isRobot()) {
        $request->redirect(new URI($params), 301);
        return true;
//        }

//        return false;
    }

    /**
     * @param array $rawUri
     * @param HttpRequest $request
     *
     * @return bool
     * @throws \SNOWGIRL_CORE\Exception
     */
    private function checkRedirectWithDuplicates(array $rawUri, HttpRequest $request): bool
    {
        $tmp = array_unique($rawUri);

        if ($rawUri != $tmp) {
            $request->redirectToRoute('default', ['action' => implode('/', $tmp)], 301);
            return true;
        }

        return false;
    }

    /**
     * @param $path
     * @param HttpRequest $request
     *
     * @return bool
     * @throws \SNOWGIRL_CORE\Exception
     */
    private function checkRedirectIndex($path, HttpRequest $request): bool
    {
        false && $path;
        $request->redirectToRoute('default', [], 301);

        return true;
    }
}