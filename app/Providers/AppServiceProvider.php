<?php

namespace App\Providers;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.custom');

        $this->registerGoogleDriveDisk();
    }

    /**
     * Daftarkan driver "google" untuk Storage/Flysystem (dipakai backup ke Google Drive).
     * Mendukung dua mode auth: service account (JSON, direkomendasikan) atau OAuth refresh token.
     */
    private function registerGoogleDriveDisk(): void
    {
        Storage::extend('google', function ($app, $config) {
            $client = new Client;
            $client->setScopes([Drive::DRIVE]);

            if (! empty($config['serviceAccount']) && is_file($config['serviceAccount'])) {
                // Mode service account (file JSON).
                $client->setAuthConfig($config['serviceAccount']);
            } else {
                // Mode OAuth (client id/secret + refresh token).
                $client->setClientId($config['clientId'] ?? '');
                $client->setClientSecret($config['clientSecret'] ?? '');
                if (! empty($config['refreshToken'])) {
                    $client->refreshToken($config['refreshToken']);
                }
            }

            $service = new Drive($client);
            $adapter = new GoogleDriveAdapter($service, $config['folder'] ?: '/');
            $filesystem = new Filesystem($adapter);

            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
