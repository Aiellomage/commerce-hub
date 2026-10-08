<?php

namespace App\Enums;

enum SyncRunType: string
{
    case CatalogImport = 'catalog_import';
    case OrderSync = 'order_sync';
    case StockUpdate = 'stock_update';
}
