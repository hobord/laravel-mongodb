<?php

namespace Hobord\MongoDb\Model;

use JsonSerializable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;

class Field implements Arrayable, Jsonable, JsonSerializable
{
    protected $schema = [];
    protected $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
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
        $this->fireModelEvent('setAttributeBefore', [$key, $value]);

        if(array_key_exists($key, $this->schema)) {
            if(!is_object($value)) {
                $value = new $this->schema[$key]($value, $this);
            }
        }

        $this->attributes[$key] = $value;

        $this->fireModelEvent('setAttributeAfter', [$key, $value]);

        return $this;
    }

    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }
    public function attributesToArray()
    {
        $attributes = $this->attributes;

        foreach ($attributes as $key => &$value) {
            if (is_subclass_of($value, 'Hobord\MongoDb\Model\Field')) {
                $value = $value->ToArray();
            }
        }

        return $attributes;
    }
    public function toArray()
    {
        return $this->attributesToArray();
    }

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
     * Fire the given event for the model.
     *
     * @param  string  $event
     * @param  bool  $halt
     * @return mixed
     */
    public function fireModelEvent($event, $halt = true)
    {
        //TODO implement events
        return;
    }
}