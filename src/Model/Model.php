<?php

namespace Hobord\MongoDb\Model;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Hobord\MongoDb\Query\Builder as QueryBuilder;
use Illuminate\Contracts\Support\Arrayable;
use MongoDB\BSON\Type;
use MongoDB\BSON\UTCDateTime;
use Carbon\Carbon;
use Hobord\MongoDb\Model\Field;

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
     * The the attributes field class names.
     *
     * @var array
     */
    protected $schema = [];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
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

    /**
     * Update the model's update timestamp.
     *
     * @return bool
     */
    public function touch()
    {
        return false; //$this->save();
    }

    /**
     * Sync the original attributes with the current.
     *
     * @return $this
     */
    public function syncOriginal()
    {
        $this->original = $this->toArray();

        return $this;
    }

    /**
     * Sync a single original attribute with its current value.
     *
     * @param  string  $attribute
     * @return $this
     */
    public function syncOriginalAttribute($attribute)
    {
        if(is_object($this->attributes[$attribute])) {
            if($this->attributes[$attribute] instanceof Type) {
                $this->original[$attribute] = (string) $attribute;
            }
            elseif($this->attributes[$attribute] instanceof Arrayable) {
                $this->original[$attribute] = $this->attributes[$attribute]->getArray();
            }
        }
        else {
            $this->original[$attribute] = $attribute;
        }

        return $this;
    }

    /**
     * Get the attributes that have been changed since last sync.
     *
     * @return array
     */
    public function getDirty()
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (! array_key_exists($key, $this->original)) {
                $dirty[$key] = $value;
            }
            elseif ($value !== $this->original[$key] &&
                ! $this->originalIsNumericallyEquivalent($key)) {
                if( $value instanceof Arrayable &&
                    count($this->diffAssocRecursive($value->toArray(), $this->original[$key]))>0) {
                    $dirty[$key] = $value;
                }
                elseif ( is_array($value) &&
                    count($this->diffAssocRecursive($value, $this->original[$key]))>0) {
                    $dirty[$key] = $value;
                }
                elseif ($value instanceof Type &&
                    $value == (string) $this->original[$key]) {
                    $dirty[$key] = $value;
                }
                else {
                    $dirty[$key] = $value;
                }
            }
        }

        return $dirty;
    }

    /**
     * Recursively computes the difference of arrays with additional index check.
     *
     * This is a version of array_diff_assoc() that supports multidimensional
     * arrays.
     *
     * @param array $array1
     *   The array to compare from.
     * @param array $array2
     *   The array to compare to.
     *
     * @return array
     *   Returns an array containing all the values from array1 that are not present
     *   in array2.
     */
    public static function diffAssocRecursive(array $array1, array $array2) {
        $difference = array();

        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!array_key_exists($key, $array2) || !is_array($array2[$key])) {
                    $difference[$key] = $value;
                }
                else {
                    $new_diff = static::diffAssocRecursive($value, $array2[$key]);
                    if (!empty($new_diff)) {
                        $difference[$key] = $new_diff;
                    }
                }
            }
            elseif (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
                $difference[$key] = $value;
            }
        }

        return $difference;
    }

    /**
     * Check the attribute path.
     * The path delimiter is the "." (key.subkey.subsubkey)
     * @param string $path
     * @return bool
     */
    public function isAttributeExists($path)
    {
        $attributes = $this->toArray();
        $path = explode('.',$path);
        if (isset($attributes[$path[0]])) {
            $current = $attributes[array_shift($path)];
            foreach ($path as $key) {
                if (isset($current[$key])) {
                    $current = $current[$key];
                }
                else {
                    return false;
                }
            }
            return true;
        }
        else {
            return false;
        }
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
                $value = new $this->schema[$key]($value, $this, null);
            }
        }

        if($value instanceof UTCDateTime) {
            $value = Carbon::createFromTimestampUTC($value->toDateTime()->getTimestamp());
        }

        if(is_array($value)) {
            $value = new Field($value, $this, null);
        }

        $this->attributes[$key] = $value;

        $this->fireModelEvent('setAttributeAfter', [$key, $value]);
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
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = $this->attributes;

        foreach ($attributes as $key => &$value) {
            if ($value instanceof Type) {
                $value = (string) $value;
            }
            if($value instanceof Arrayable) {
                $value = $value->ToArray();
            }
        }

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
     * Fire the given event for the model.
     *
     * @param  string  $event
     * @param  bool  $halt
     * @return mixed
     */
    protected function fireModelEvent($event, $halt = true)
    {
        if (! isset(static::$dispatcher)) {
            return true;
        }

        // We will append the names of the class to the event to distinguish it from
        // other model events that are fired, allowing us to listen on each model
        // event set individually instead of catching event for all the models.
        $event = "mogodbmodel.{$event}: ".static::class;

        $method = $halt ? 'until' : 'fire';

        return static::$dispatcher->$method($event, $this);
    }
}
