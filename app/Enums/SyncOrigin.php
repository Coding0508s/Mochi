<?php

namespace App\Enums;

enum SyncOrigin: string
{
    case Local = 'local';
    case ExternalIngest = 'external_ingest';
}
