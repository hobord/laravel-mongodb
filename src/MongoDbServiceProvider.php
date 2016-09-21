<?php
namespace Hobord\MongoDb;

use Illuminate\Support\ServiceProvider;

class MongoDbServiceProvider extends ServiceProvider
{
    public function boot()
    {
    }

    public function register()
    {
        // Add database driver.
        $this->app->resolving('db', function ($db) {
            $db->extend('mongodb', function ($config) {
                return new Connection($config);
            });
        });
    }
}