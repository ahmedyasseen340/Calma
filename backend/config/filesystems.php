<?php

/*
 * Two places where files live:
 *   "local"  = PRIVATE files: voice recordings, the doctors' ID card and certificate
 *   "public" = files everybody may open: doctor photos, article covers, calming audios
 *
 * On a developer's laptop both are folders (default). On the server both are Supabase Storage buckets,
 * reached through its S3-compatible API: set PRIVATE_STORAGE=s3 and PUBLIC_STORAGE=s3 (see docs/deployment.md).
 * The names "local" and "public" never change, so the code and the tests work the same in both cases.
 * While testing (APP_ENV=testing) the folders are always used, so a test can never touch the real buckets.
 */
$testing = env('APP_ENV') === 'testing';
$private = $testing ? 'local' : env('PRIVATE_STORAGE', 'local');
$public = $testing ? 'local' : env('PUBLIC_STORAGE', 'local');

$supabase = fn (string $bucket, ?string $url = null) => array_filter([
    'driver' => 's3',
    'key' => env('SUPABASE_S3_KEY'),
    'secret' => env('SUPABASE_S3_SECRET'),
    'region' => env('SUPABASE_S3_REGION', 'eu-west-1'),
    'bucket' => $bucket,
    'endpoint' => env('SUPABASE_S3_ENDPOINT'),   // https://<project-ref>.storage.supabase.co/storage/v1/s3
    'use_path_style_endpoint' => true,            // Supabase needs path-style addresses
    'url' => $url,                                // public base URL (public bucket only)
    'throw' => true,                              // fail loudly instead of returning false
], fn ($value) => $value !== null);

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => $private === 's3'
            ? $supabase(env('SUPABASE_PRIVATE_BUCKET', 'calma-private'))
            : [
                'driver' => 'local',
                'root' => storage_path('app/private'),
                'serve' => true,
                'throw' => false,
                'report' => false,
            ],

        'public' => $public === 's3'
            ? $supabase(env('SUPABASE_PUBLIC_BUCKET', 'calma-public'), env('SUPABASE_PUBLIC_URL'))
            : [
                'driver' => 'local',
                'root' => storage_path('app/public'),
                'url' => env('APP_URL', 'http://localhost').'/storage',
                'visibility' => 'public',
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

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
