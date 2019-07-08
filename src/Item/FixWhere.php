<?php

namespace SNOWGIRL_SHOP\Item;

use SNOWGIRL_CORE\Service\Storage\Query\Expr;
use SNOWGIRL_CORE\App;
use SNOWGIRL_SHOP\Entity\Import\Source as ImportSource;

class FixWhere
{
    /** @var App */
    protected $app;
    protected $createdAtFrom;
    protected $createdAtTo;
    protected $updatedAtFrom;
    protected $updatedAtFromWithNulls;
    protected $updatedAtIsNull;
    protected $updatedAtTo;
    protected $updatedAtToWithNulls;
    protected $orBetweenCreatedAndUpdated;
    /** @var ImportSource[] */
    protected $sources = [];

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function setCreatedAtFrom($v)
    {
        $this->createdAtFrom = $v;
        $this->dropCache();
        return $this;
    }

    public function setCreatedAtTo($v)
    {
        $this->createdAtTo = $v;
        $this->dropCache();
        return $this;
    }

    public function setUpdatedAtFrom($v, $withNulls = false)
    {
        $this->updatedAtFrom = $v;
        $this->updatedAtFromWithNulls = $withNulls;
        $this->dropCache();
        return $this;
    }

    public function setUpdatedAtTo($v, $withNulls = false)
    {
        $this->updatedAtTo = $v;
        $this->updatedAtToWithNulls = $withNulls;
        $this->dropCache();
        return $this;
    }

    public function setUpdatedAtIsNull($v)
    {
        $this->updatedAtIsNull = !!$v;
        $this->dropCache();
        return $this;
    }

    public function setSources($v)
    {
        $v = is_array($v) ? $v : [$v];
        $this->sources = array_map(function ($source) {
            return $source instanceof ImportSource ? $source : $this->app->managers->sources->find($source);
        }, $v);
        $this->dropCache();
        return $this;
    }

    public function setOrBetweenCreatedAndUpdated($v)
    {
        $this->orBetweenCreatedAndUpdated = !!$v;
        $this->dropCache();
        return $this;
    }

    protected function formatDate($date)
    {
        $format = 'Y-m-d H:i:s';

        if (is_int($date)) {
            return date($format, $date);
        }

        if (is_string($date)) {
            //@todo check format
            return $date;
        }

        if ($date instanceof \DateTime) {
            return $date->format($format);
        }

        return false;
    }

    /**
     * @return array
     */
    public function create()
    {
        $where = [];
        $orWhere = [];

        if ($this->createdAtFrom || $this->createdAtTo) {
            $q = $this->app->services->rdbms->quote('created_at');

            if ($tmp = $this->formatDate($this->createdAtFrom)) {
                $where[] = new Expr($q . ' > ?', $tmp);
            }

            if ($tmp = $this->formatDate($this->createdAtTo)) {
                $where[] = new Expr($q . ' < ?', $tmp);
            }
        }

        if ($where && $this->orBetweenCreatedAndUpdated) {
            $orWhere[] = $where;
            $where = [];
        }

        if ($this->updatedAtFrom || $this->updatedAtTo || $this->updatedAtIsNull) {
            $q = $this->app->services->rdbms->quote('updated_at');

            if ($tmp = $this->formatDate($this->updatedAtFrom)) {
                $where[] = new Expr(($this->updatedAtFromWithNulls ? ($q . ' IS NULL OR ') : '') . $q . ' > ?', $tmp);
            }

            if ($tmp = $this->formatDate($this->updatedAtTo)) {
                $where[] = new Expr(($this->updatedAtToWithNulls ? ($q . ' IS NULL OR ') : '') . $q . ' < ?', $tmp);
            }

            if ($this->updatedAtIsNull) {
                $where[] = new Expr($q . ' IS NULL');
            }
        }

        if ($orWhere) {
            if ($where) {
                $orWhere[] = $where;
                $where = array_map(function ($where) {
                    return $this->mergeExprsArray($where, '($1) AND ($2)');
                }, $orWhere);

                $where = [$this->mergeExprsArray($where, '($1) OR ($2)')];
            } else {
                $where = $orWhere;
            }
        }

        if ($this->sources) {
            //@todo change when vendor has more then one source...
            $where = array_merge(['vendor_id' => array_map(function ($source) {
                /** @var ImportSource $source */
                return $source->getVendorId();
            }, $this->sources)], $where);
        }

        return $where;
    }

    protected $cache;

    public function get()
    {
        return $this->cache ?: $this->cache = $this->create();
    }

    public function dropCache()
    {
        $this->cache = null;
        return $this;
    }

    /**
     * @param Expr   $expr1
     * @param Expr   $expr2
     * @param string $template
     *
     * @return Expr
     */
    protected function mergeExprs(Expr $expr1, Expr $expr2, $template = '($1) AND ($2)')
    {
        $args = [];

        $pos1 = strpos($template, '$1');
        $pos2 = strpos($template, '$2');

        $query = str_replace('$1', $expr1->getQuery(), $template);
        $query = str_replace('$2', $expr2->getQuery(), $query);

        $args[] = $query;

        $args = array_merge($args, $pos1 < $pos2 ? $expr1->getParams() : $expr2->getParams());
        $args = array_merge($args, $pos1 < $pos2 ? $expr2->getParams() : $expr1->getParams());

        return new Expr(...$args);
    }

    /**
     * @param array  $input
     * @param string $template
     *
     * @return Expr
     */
    protected function mergeExprsArray(array $input, $template = '($1) AND ($2)')
    {
        /** @var Expr $output */
        $output = array_shift($input);

        foreach ($input as $v) {
            $output = $this->mergeExprs($output, $v, $template);
        }

        return $output;
    }
}