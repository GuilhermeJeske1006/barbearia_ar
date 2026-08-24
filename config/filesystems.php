<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            // Laravel Cloud (e qualquer host com filesystem efêmero) roda várias
            // instâncias sem disco local compartilhado: um arquivo escrito numa
            // instância some/não existe pras outras. Segue FILESYSTEM_DISK (já
            // provisionado automaticamente pelo Object Storage do Cloud, que é
            // Cloudflare R2 por baixo) pra virar S3 nesses ambientes, sem exigir
            // trocar nenhum Storage::disk('public') espalhado pelo código. Sem
            // essa env, comportamento local não muda.
            'driver' => env('FILESYSTEM_DISK', 'local') === 's3' ? 's3' : 'local',
            'root' => storage_path('app/public'),
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'url' => env('AWS_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/storage'),
            // R2 gerencia visibilidade só no nível do bucket (marque o bucket como
            // "Public" no dashboard do Cloud) e rejeita ACL 'public-read' por
            // objeto com NotImplemented. Usa 'private' explícito (não null — o
            // FilesystemManager troca null pelo default 'public' via ??) pra
            // nenhum upload nesse disco acabar pedindo ACL pública sem querer.
            'visibility' => env('FILESYSTEM_DISK', 'local') === 's3' ? 'private' : 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
