<?php

namespace Hobord\MongoDb\Model;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Hobord\MongoDb\Query\Builder as QueryBuilder;
use Illuminate\Contracts\Support\Arrayable;
use MongoDB\BSON\Type;
use MongoDB\BSON\UTCDateTime;
use Carbon\Carbon;
use Hobord\MongoDb\Model\Field;

abstract class Model extends SimpleModel
{
     /**
     * The the attributes field class names.
     *
     * @var array
     */
    protected $schema = [];

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
            $this->original[$attribute] = $this->attributes[$attribute];
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
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if ($this->hasSetMutator($key)) {
            $method = 'set'.Str::studly($key).'Attribute';

            return $this->{$method}($value);
        }
        if ( $key == 'id' ) {
            $key = "_id";
        }
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

        return $this;
    }
}
