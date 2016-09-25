<?php

namespace Hobord\MongoDb\Model;

use JsonSerializable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Events\Dispatcher;

class Field implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The the attributes field class names.
     *
     * @var array
     */
    protected $schema = [];

    /**
     * The filed owner object.
     *
     * @var object
     */
    protected $model = null;

    /**
     * The field's attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The event dispatcher instance.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected static $dispatcher;


    public function __construct(array $attributes = [], Model $model)
    {
        $this->model = $model;
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

    /**
     * Fill attributes on the model.
     *
     * @param  array $attributes
     * @return Field
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Convert private attributes to array.
     *
     * @return array
     */
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

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributesToArray();
    }

    /**
     * Convert the object to its JSON representation.
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
     * Register an observer with the Model.
     *
     * @param  object|string  $class
     * @param  int  $priority
     * @return void
     */
    public static function observe($class, $priority = 0)
    {
        $instance = new static;

        $className = is_string($class) ? $class : get_class($class);

        // When registering a model observer, we will spin through the possible events
        // and determine if this observer has that method. If it does, we will hook
        // it into the model's event system, making it convenient to watch these.
        foreach ($instance->getObservableEvents() as $event) {
            if (method_exists($class, $event)) {
                static::registerModelEvent($event, $className.'@'.$event, $priority);
            }
        }
    }

    /**
     * Remove all of the event listeners for the model.
     *
     * @return void
     */
    public static function flushEventListeners()
    {
        if (! isset(static::$dispatcher)) {
            return;
        }

        $instance = new static;

        foreach ($instance->getObservableEvents() as $event) {
            static::$dispatcher->forget("eloquent.{$event}: ".static::class);
        }
    }

    /**
     * Register a model event with the dispatcher.
     *
     * @param  string  $event
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    protected static function registerModelEvent($event, $callback, $priority = 0)
    {
        if (isset(static::$dispatcher)) {
            $name = static::class;

            static::$dispatcher->listen("eloquent.{$event}: {$name}", $callback, $priority);
        }
    }

    /**
     * Get the observable event names.
     *
     * @return array
     */
    public function getObservableEvents()
    {
        return array_merge(
            [
                'creating', 'created', 'updating', 'updated',
                'deleting', 'deleted', 'saving', 'saved',
                'restoring', 'restored',
            ],
            $this->observables
        );
    }

    /**
     * Set the observable event names.
     *
     * @param  array  $observables
     * @return $this
     */
    public function setObservableEvents(array $observables)
    {
        $this->observables = $observables;

        return $this;
    }

    /**
     * Add an observable event name.
     *
     * @param  array|mixed  $observables
     * @return void
     */
    public function addObservableEvents($observables)
    {
        $observables = is_array($observables) ? $observables : func_get_args();

        $this->observables = array_unique(array_merge($this->observables, $observables));
    }

    /**
     * Remove an observable event name.
     *
     * @param  array|mixed  $observables
     * @return void
     */
    public function removeObservableEvents($observables)
    {
        $observables = is_array($observables) ? $observables : func_get_args();

        $this->observables = array_diff($this->observables, $observables);
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
        if (! isset(static::$dispatcher)) {
            return true;
        }
        $event = "eloquent.{$event}: ".static::class;

        $method = $halt ? 'until' : 'fire';

        return static::$dispatcher->$method($event, $this);
    }
    /**
     * Get the event dispatcher instance.
     *
     * @return \Illuminate\Contracts\Events\Dispatcher
     */
    public static function getEventDispatcher()
    {
        return static::$dispatcher;
    }

    /**
     * Set the event dispatcher instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @return void
     */

    public static function setEventDispatcher(Dispatcher $dispatcher)
    {
        static::$dispatcher = $dispatcher;
    }

    /**
     * Unset the event dispatcher for models.
     *
     * @return void
     */
    public static function unsetEventDispatcher()
    {
        static::$dispatcher = null;
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
}