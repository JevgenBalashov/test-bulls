<?php

namespace App\Providers;

use App\Services\GoogleSheets\GoogleSheetsClient;
use App\Services\GoogleSheets\SheetReadException;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Sheets::class, function (): Sheets {
            $path = config('services.google.credentials');

            if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
                $path = base_path($path);
            }

            if (! is_file($path)) {
                throw SheetReadException::missingCredentials($path);
            }

            $client = new Client;
            $client->setApplicationName(config('app.name'));
            $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
            $client->setAuthConfig($path);

            return new Sheets($client);
        });

        $this->app->bind(GoogleSheetsClient::class, fn (Application $app) => new GoogleSheetsClient(
            fn (): Sheets => $app->make(Sheets::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
