<?php

namespace Hobord\MongoDb\Model;

use Closure;
use InvalidArgumentException;
use JsonSerializable;
use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\Eloquent\Scope;
use Hobord\MongoDb\Query\Builder as QueryBuilder;

abstract class Model implements ArrayAccess, Arrayable, Jsonable, JsonSerializable
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The number of models to return for pagination.
     *
     * @var int
     */
    protected $perPage = 15;

    /**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected static $resolver;

    /**
     * The databes schema definiton.
     *
     * @var array
     */
    protected $schema = [];

    /**
     * the object fields.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Is object changed?
     *
     * @var boolean
     */
    protected $isChanged = false;

    /**
     * Parent object;
     *
     * @var object
     */
    protected $parent_object = null;

    /**
     * The array of booted models.
     *
     * @var array
     */
    protected static $booted = [];

    /**
     * The array of global scopes on the model.
     *
     * @var array
     */
    protected static $globalScopes = [];


    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();

//        $this->syncOriginal();

        $this->fill($attributes);
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted()
    {
        if (! isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            $this->fireModelEvent('booting', false);

            static::boot();

            $this->fireModelEvent('booted', false);
        }
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        static::bootTraits();
    }

    /**
     * Boot all of the bootable traits on the model.
     *
     * @return void
     */
    protected static function bootTraits()
    {
        $class = static::class;

        foreach (class_uses_recursive($class) as $trait) {
            if (method_exists($class, $method = 'boot'.class_basename($trait))) {
                forward_static_call([$class, $method]);
            }
        }
    }

    /**
     * Clear the list of booted models so they will be re-booted.
     *
     * @return void
     */
    public static function clearBootedModels()
    {
        static::$booted = [];
        static::$globalScopes = [];
    }

    /**
     * Register a new global scope on the model.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|\Closure|string  $scope
     * @param  \Closure|null  $implementation
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public static function addGlobalScope($scope, Closure $implementation = null)
    {
        if (is_string($scope) && $implementation !== null) {
            return static::$globalScopes[static::class][$scope] = $implementation;
        }

        if ($scope instanceof Closure) {
            return static::$globalScopes[static::class][spl_object_hash($scope)] = $scope;
        }

        if ($scope instanceof Scope) {
            return static::$globalScopes[static::class][get_class($scope)] = $scope;
        }

        throw new InvalidArgumentException('Global scope must be an instance of Closure or Scope.');
    }

    /**
     * Determine if a model has a global scope.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|string  $scope
     * @return bool
     */
    public static function hasGlobalScope($scope)
    {
        return ! is_null(static::getGlobalScope($scope));
    }

    /**
     * Get a global scope registered with the model.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|string  $scope
     * @return \Illuminate\Database\Eloquent\Scope|\Closure|null
     */
    public static function getGlobalScope($scope)
    {
        if (! is_string($scope)) {
            $scope = get_class($scope);
        }

        return Arr::get(static::$globalScopes, static::class.'.'.$scope);
    }

    /**
     * Get the global scopes for this class instance.
     *
     * @return array
     */
    public function getGlobalScopes()
    {
        return Arr::get(static::$globalScopes, static::class, []);
    }

    /**
     * Get the database connection for the model.
     *
     * @return \Illuminate\Database\Connection
     */
    public function getConnection()
    {
        return static::resolveConnection($this->getConnectionName());
    }

    /**
     * Get the current connection name for the model.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connection;
    }

    /**
     * Set the connection associated with the model.
     *
     * @param  string  $name
     * @return $this
     */
    public function setConnection($name)
    {
        $this->connection = $name;

        return $this;
    }

    /**
     * Resolve a connection instance.
     *
     * @param  string|null  $connection
     * @return \Illuminate\Database\Connection
     */
    public static function resolveConnection($connection = null)
    {
        return static::$resolver->connection($connection);
    }

    /**
     * Get the connection resolver instance.
     *
     * @return \Illuminate\Database\ConnectionResolverInterface
     */
    public static function getConnectionResolver()
    {
        return static::$resolver;
    }

    /**
     * Set the connection resolver instance.
     *
     * @param  \Illuminate\Database\ConnectionResolverInterface  $resolver
     * @return void
     */
    public static function setConnectionResolver(Resolver $resolver)
    {
        static::$resolver = $resolver;
    }

    /**
     * Unset the connection resolver for models.
     *
     * @return void
     */
    public static function unsetConnectionResolver()
    {
        static::$resolver = null;
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Hobord\MongoDb\Query\Builder  $query
     * @return \Hobord\MongoDb\Query\Builder|static
     */
    public function newBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Begin querying the model.
     *
     * @return \Hobord\MongoDb\Query\Builder
     */
    public static function query()
    {
        return (new static)->newQuery();
    }

    public function newQuery()
    {
        $builder = $this->newQueryWithoutScopes();

        foreach ($this->getGlobalScopes() as $identifier => $scope) {
            $builder->withGlobalScope($identifier, $scope);
        }

        return $builder;
    }

    /**
     * Get a new query instance without a given scope.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|string  $scope
     * @return \Hobord\MongoDb\Query\Builder
     */
    public function newQueryWithoutScope($scope)
    {
        $builder = $this->newQuery();

        return $builder->withoutGlobalScope($scope);
    }

    /**
     * Get a new query builder that doesn't have any global scopes.
     *
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newQueryWithoutScopes()
    {
        $builder = $this->newMongoDbBuilder(
            $this->newBaseQueryBuilder()
        );

        // Once we have the query builders, we will set the model instances so the
        // builder can easily access any information it may need from the model
        // while it is constructing and executing various queries against it.
        return $builder->setModel($this)->with($this->with);
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newMongoDbBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();

        $grammar = $conn->getQueryGrammar();

        return new QueryBuilder($conn, $grammar, $conn->getPostProcessor());
    }

    /**
     * Begin querying the model on a given connection.
     *
     * @param  string|null  $connection
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function on($connection = null)
    {
        // First we will just create a fresh instance of this model, and then we can
        // set the connection on the model so that it is be used for the queries
        // we execute, as well as being set on each relationship we retrieve.
        $instance = new static;

        $instance->setConnection($connection);

        return $instance->newQuery();
    }

    /**
     * Update model changed status
     *
     * @return bool
     */
    public function touch()
    {
        $this->isChanged = true;
        if($this->parent_object != null) {
            $this->parent_object->touch();
        }
    }

    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
    }

    public function setAttribute($key, $value)
    {
        $this->fireModelEvent('setAttributeBefore', [$key, $value]);

        if(array_key_exists($key, $this->schema)) {
            if(is_array($value)) {
                $value = new $this->schema[$key]($value, $this);
            }
        }

        $this->attributes[$key] = $value;

        $this->fireModelEvent('setAttributeAfter', [$key, $value]);
        $this->touch();
    }

    public function fireModelEvent($event, $parameters)
    {
        //TODO implement events
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Fill attributes on the model.
     *
     * @param  array $attributes
     * @return void
     */
    public function fill($attributes)
    {
        foreach ($attributes as $key => $attribute) {
            $this->setAttribute($key, $attribute);
        }
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $result = [];
        foreach ($this->attributes as $key => $attribute) {
            if(is_object($attribute)) {
                $attribute = $attribute->toArray();
            }
            $result[$key] = $attribute;
        }
        return $result;
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }

}