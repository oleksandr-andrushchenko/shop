<?php

namespace SNOWGIRL_SHOP\Manager;

use SNOWGIRL_CORE\Manager;
use SNOWGIRL_SHOP\Manager\Category\Entity as CategoryEntity;
use SNOWGIRL_SHOP\Manager\Category\Child as CategoryChild;
use SNOWGIRL_SHOP\Manager\Page\Catalog as PageCatalog;
use SNOWGIRL_SHOP\Manager\Page\Catalog\Custom as PageCatalogCustom;
use SNOWGIRL_SHOP\Manager\Import\Source;
use SNOWGIRL_SHOP\Manager\Item\Color as ItemToColor;
use SNOWGIRL_SHOP\Manager\Item\Size as ItemToSize;
use SNOWGIRL_SHOP\Manager\Item\Material as ItemToMaterial;
use SNOWGIRL_SHOP\Manager\Item\Tag as ItemToTag;
use SNOWGIRL_SHOP\Manager\Item\Season as ItemToSeason;
use SNOWGIRL_SHOP\Manager\Item\Attr as ItemAttr;
use SNOWGIRL_SHOP\Manager\Item\Redirect as ItemRedirect;
use SNOWGIRL_SHOP\Manager\Import\History as ImportHistory;
use SNOWGIRL_SHOP\Manager\Item\Image as ItemImage;

/**
 * Class Builder
 *
 * @property Item              items
 * @property Category          categories
 * @property Brand             brands
 * @property Color             colors
 * @property Tag               tags
 * @property Size              sizes
 * @property Material          materials
 * @property Country           countries
 * @property PageCatalog       catalog
 * @property PageCatalogCustom catalogCustom
 * @property Vendor            vendors
 * @property Season            seasons
 * @property Source            sources
 * @property CategoryEntity    categoriesToEntities
 * @property CategoryChild     categoriesToChildren
 * @property ItemToColor       itemsToColors
 * @property ItemToSize        itemsToSizes
 * @property ItemToMaterial    itemsToMaterials
 * @property ItemToTag         itemsToTags
 * @property ItemToSeason      itemsToSeasons
 * @property Stock             stock
 * @property ItemRedirect      itemRedirects
 * @property ImportHistory     importHistory
 * @property ItemImage         itemImages
 * @method Manager|Term get($class)
 * @method Manager|Term getByEntityClass($class)
 * @method Manager|ItemAttr getByTable($table)
 * @package SNOWGIRL_SHOP\Manager
 */
class Builder extends \SNOWGIRL_CORE\Manager\Builder
{
    protected function _get($k)
    {
        switch ($k) {
            case 'items':
                return $this->get(Item::class);
            case 'categories':
                return $this->get(Category::class);
            case 'brands':
                return $this->get(Brand::class);
            case 'colors':
                return $this->get(Color::class);
            case 'tags':
                return $this->get(Tag::class);
            case 'sizes':
                return $this->get(Size::class);
            case 'materials':
                return $this->get(Material::class);
            case 'countries':
                return $this->get(Country::class);
            case 'catalog':
                return $this->get(PageCatalog::class);
            case 'catalogCustom':
                return $this->get(PageCatalogCustom::class);
            case 'vendors':
                return $this->get(Vendor::class);
            case 'seasons':
                return $this->get(Season::class);
            case 'sources':
                return $this->get(Source::class);
            case 'categoriesToEntities':
                return $this->get(CategoryEntity::class);
            case 'categoriesToChildren':
                return $this->get(CategoryChild::class);
            case 'itemsToColors':
                return $this->get(ItemToColor::class);
            case 'itemsToSizes':
                return $this->get(ItemToSize::class);
            case 'itemsToMaterials':
                return $this->get(ItemToMaterial::class);
            case 'itemsToTags':
                return $this->get(ItemToTag::class);
            case 'itemsToSeasons':
                return $this->get(ItemToSeason::class);
            case 'stock':
                return $this->get(Stock::class);
            case 'itemRedirects':
                return $this->get(ItemRedirect::class);
            case 'itemImages':
                return $this->get(ItemImage::class);
            case 'importHistory':
                return $this->get(ImportHistory::class);
            default:
                return parent::_get($k);
        }
    }
}