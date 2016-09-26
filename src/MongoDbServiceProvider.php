<?php
namespace Hobord\MongoDb;

use Hobord\MongoDb\Model\Field;
use Illuminate\Support\ServiceProvider;
use Hobord\MongoDb\Model\Model;

class MongoDbServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Model::setConnectionResolver($this->app['db']);

        Field::setEventDispatcher($this->app['events']);
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