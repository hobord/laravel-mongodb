<?php

namespace Hobord\MongoDb\Model;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Hobord\MongoDb\Query\Builder as QueryBuilder;
use MongoDB\BSON\ObjectID;

abstract class Model extends BaseModel
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = '_id';

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
     * The the attributes field class names.
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

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public function touch()
    {
//        return parent::touch(); // TODO: Change the autogenerated stub
    }

    public function isDirty($attributes = null)
    {
        return true; //TODO always dirty?
//        return parent::isDirty($attributes); // TODO: Change the autogenerated stub
    }
    public function getDirty()
    {
        return $this->attributes; //TODO always dirty?
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
        if ( $key == 'id' ) {
            return (string) $this->attributes['_id'];
        }
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        // Convert _id to ObjectID.
        if ($key == '_id' and is_string($value)) {
            $builder = $this->newBaseQueryBuilder();
            $value = $builder->convertKey($value);
        }

        $this->fireModelEvent('setAttributeBefore', [$key, $value]);

        if(array_key_exists($key, $this->schema)) {
            if(!is_object($value)) {
                $value = new $this->schema[$key]($value, $this);
            }
        }

        $this->attributes[$key] = $value;

        $this->fireModelEvent('setAttributeAfter', [$key, $value]);
        $this->touch();
    }

    public function setRawAttributes(array $attributes, $sync = false)
    {
        foreach ($attributes as $key => $attribute) {
            $this->setAttribute($key, $attribute);
        }
        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /**
    * Fill attributes on the model.
    *
    * @param  array $attributes
    * @return void
    */
    public function fill(array $attributes = [])
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
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $attributes = $this->attributesToArray();

        return $attributes;
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return Builder
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();
        return new QueryBuilder($connection, $connection->getPostProcessor());
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = $this->attributes;

        foreach ($attributes as $key => &$value) {
            if ($value instanceof ObjectID) {
                $value = (string) $value;
            }
            if (is_subclass_of($value, 'Hobord\MongoDb\Model\Field')) {
                $value = $value->ToArray();
            }
        }

        return $attributes;
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Unset method
        if ($method == 'unset') {
            return call_user_func_array([$this, 'drop'], $parameters);
        }
        return parent::__call($method, $parameters);
    }
}