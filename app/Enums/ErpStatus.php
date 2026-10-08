<?php

namespace App\Enums;

enum ErpStatus: string
{
    case Pending = 'pending';
    case Exported = 'exported';
    case Failed = 'failed';
}
