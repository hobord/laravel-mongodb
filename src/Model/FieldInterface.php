<?php
namespace Hobord\MongoDb\Model;

use JsonSerializable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;

interface FieldInterface extends Arrayable, Jsonable, JsonSerializable
{
    /**
     * Fill attributes on the model.
     *
     * @param  array $attributes
     * @return Field
     */
    public function fill(array $attributes);

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray();

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int $options
     * @return string
     */
    public function toJson($options = 0);

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize();
}