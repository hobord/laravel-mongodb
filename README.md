# laravel-mongodb

And add the service provider in config/app.php:

Jenssegers\Mongodb\MongodbServiceProvider::class,

##Configuration
And add a new mongodb connection:
```
'mongodb' => [
    'driver'   => 'mongodb',
    'host'     => env('DB_HOST', 'localhost'),
    'port'     => env('DB_PORT', 27017),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'options' => [
        'database' => 'admin' // sets the authentication database required by mongo 3
    ]
],
```
You can connect to multiple servers or replica sets with the following configuration:

```
'mongodb' => [
    'driver'   => 'mongodb',
    'host'     => ['server1', 'server2'],
    'port'     => env('DB_PORT', 27017),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'options'  => ['replicaSet' => 'replicaSetName']
],

```
