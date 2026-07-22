<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Backup
    |--------------------------------------------------------------------------
    |
    | 배포(운영) DB 주간 백업. 로컬/스테이징에서는 DB_BACKUP_ENABLED=false 유지.
    | 스케줄은 production + enabled 일 때만 실행됩니다.
    |
    */

    'enabled' => (bool) env('DB_BACKUP_ENABLED', false),

    /*
    | filesystems.php 의 disk 이름. private local 또는 s3.
    */
    'disk' => env('DB_BACKUP_DISK', 'local'),

    /*
    | disk 루트 기준 상대 경로.
    */
    'path' => env('DB_BACKUP_PATH', 'backups'),

    /*
    | 보관할 최근 백업 파일 개수 (새 백업 포함).
    */
    'keep' => max(1, (int) env('DB_BACKUP_KEEP', 4)),

    /*
    | null 이면 config('database.default') 연결을 사용.
    */
    'connection' => env('DB_BACKUP_CONNECTION'),

    'mysqldump_path' => env('DB_BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

    'gzip' => filter_var(env('DB_BACKUP_GZIP', true), FILTER_VALIDATE_BOOL),

];
