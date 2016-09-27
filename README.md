# laravel-mongodb

And add the service provider in config/app.php:

Hobord\MongoDb\MongodbServiceProvider::class,

##Configuration
And add a new mongodb connection:
```
'mongodb' => [
    'driver'   => 'mongodb',
    'host'     => env('MONGO_DB_HOST', 'localhost'),
    'port'     => env('MONGO_DB_PORT', 27017),
    'database' => env('MONGO_DB_DATABASE'),
    'username' => env('MONGO_DB_USERNAME'),
    'password' => env('MONGO_DB_PASSWORD'),
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
    'port'     => env('MONGO_DB_PORT', 27017),
    'database' => env('MONGO_DB_DATABASE'),
    'username' => env('MONGO_DB_USERNAME'),
    'password' => env('MONGO_DB_PASSWORD'),
    'options'  => ['replicaSet' => 'replicaSetName']
],

```


##Example usage

```
namespace App;
use Hobord\MongoDb\Model\Model;

class TestModel extends Model
{
    protected $table = "test_collection";

    protected $schema = [
        'pricing' => 'App\PricingField'
    ];
}

#####

namespace App;

use Hobord\MongoDb\Model\Field;

class PricingField extends Field
{

}

#####


TestModel::create([
    'sku'=> '00e8da9c',
    'pricing' => [
        'list' => 500,
        'retail' => 600,
        'action' => 700
    ]
]);

$test = TestModel::where('sku', '00e8da9c')->first();
$test->pricing->retail = 999;
$test->karma = "ok";
$test->save();

$test = TestModel::where('pricing.retail', '>', 600)->skip(1)->take(1)->get();

```
