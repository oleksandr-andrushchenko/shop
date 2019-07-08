<?php

namespace SNOWGIRL_SHOP;

class RBAC extends \SNOWGIRL_CORE\RBAC
{
    public const PERM_CATALOG_PAGE = 101;
    public const PERM_CATEGORIES_PAGE = 102;
    public const PERM_CATEGORY_FIXES_PAGE = 103;
    public const PERM_GENERATE_PAGES = 104;
    public const PERM_IMPORT_SOURCE_PAGE = 105;
    public const PERM_COPY_IMPORT_SOURCE = 106;
    public const PERM_DELETE_IMPORT_SOURCE = 107;
    public const PERM_RUN_IMPORT = 108;
    public const PERM_REFRESH_IMPORT_SOURCE = 109;
    public const PERM_SAVE_IMPORT_SOURCE_FILTER = 110;
    public const PERM_SAVE_IMPORT_SOURCE_MAIN = 111;
    public const PERM_SAVE_IMPORT_SOURCE_MAPPING = 112;
    public const PERM_TOGGLE_IMPORT_SOURCE_CRON = 113;
    public const PERM_ITEM_FIXES_PAGE = 114;
    public const PERM_OFFERS_PAGE = 115;

    public const PERM_ADD_UPDATE_CATALOG_SEO_TEXT = 116;
    public const PERM_ACTIVATE_OWN_CATALOG_SEO_TEXT = 117;
    public const PERM_UPDATE_FOREIGN_CATALOG_SEO_TEXT = 118;
    public const PERM_ACTIVATE_FOREIGN_CATALOG_SEO_TEXT = 119;
    public const PERM_DELETE_OWN_CATALOG_SEO_TEXT = 120;
    public const PERM_DELETE_FOREIGN_CATALOG_SEO_TEXT = 121;
    public const PERM_TRANSFER_ITEMS_BY_ATTRS = 122;

    public const PERM_BUILD_CATEGORIES_TREE = 123;
    public const PERM_MODIFY_CATALOG_META = 124;
}