<?php

namespace SNOWGIRL_SHOP\Util;

/**
 * Class Builder
 *
 * @property Attr attrs
 * @property Brand brands
 * @property Catalog catalog
 * @method Catalog catalog(bool $debug = null)
 * @property Category categories
 * @property Color colors
 * @property Import import
 * @property Item items
 * @method Item items(bool $debug = null)
 * @property Site sites
 * @property Tag tags
 * @property Image images
 * @property Source sources
 * @package SNOWGIRL_SHOP\Util
 */
class Builder extends \SNOWGIRL_CORE\Util\Builder
{
    protected function _get($k)
    {
        switch ($k) {
            case 'attrs':
                return $this->get(Attr::class);
            case 'brands':
                return $this->get(Brand::class);
            case 'catalog':
                return $this->get(Catalog::class);
            case 'categories':
                return $this->get(Category::class);
            case 'colors':
                return $this->get(Color::class);
            case 'import':
                return $this->get(Import::class);
            case 'items':
                return $this->get(Item::class);
            case 'sites':
                return $this->get(Site::class);
            case 'tags':
                return $this->get(Tag::class);
            case 'sources':
                return $this->get(Source::class);
            default:
                return parent::_get($k);
        }
    }

    protected function _call($fn, array $args)
    {
        switch ($fn) {
            case 'items':
                return $this->get(Item::class, $args[0] ?? null);
            case 'catalog':
                return $this->get(Catalog::class, $args[0] ?? null);
            default:
                return parent::_call($fn, $args);
        }
    }
}