<?php namespace Hobord\MongoDb\Model;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use MongoDB\Driver\Cursor;
use MongoDB\Model\BSONDocument;

class Builder extends EloquentBuilder
{
    /**
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = [
        'toSql', 'insert', 'insertGetId', 'pluck',
        'count', 'min', 'max', 'avg', 'sum', 'exists', 'push', 'pull',
    ];

    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @param  array  $options
     * @return int
     */
    public function update(array $values, array $options = [])
    {
        return $this->query->update($this->addUpdatedAtColumn($values), $options);
    }

    /**
     * Insert a new record into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        return parent::insert($values);
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        return parent::insertGetId($values, $sequence);
    }

    /**
     * Delete a record from the database.
     *
     * @return mixed
     */
    public function delete()
    {
        return parent::delete();
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        return parent::increment($column, $amount, $extra);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        return parent::decrement($column, $amount, $extra);
    }

    /**
     * Add the "has" condition where clause to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $hasQuery
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $relation
     * @param  string  $operator
     * @param  int  $count
     * @param  string  $boolean
     * @return \Illuminate\Database\Eloquent\Builder
     */
//    protected function addHasWhere(EloquentBuilder $hasQuery, Relation $relation, $operator, $count, $boolean)
//    {
//        $query = $hasQuery->getQuery();
//
//        // Get the number of related objects for each possible parent.
//        $relations = $query->pluck($relation->getHasCompareKey());
//        $relationCount = array_count_values(array_map(function ($id) {
//            return (string) $id; // Convert Back ObjectIds to Strings
//        }, is_array($relations) ? $relations : $relations->toArray()));
//
//        // Remove unwanted related objects based on the operator and count.
//        $relationCount = array_filter($relationCount, function ($counted) use ($count, $operator) {
//            // If we are comparing to 0, we always need all results.
//            if ($count == 0) {
//                return true;
//            }
//
//            switch ($operator) {
//                case '>=':
//                case '<':
//                    return $counted >= $count;
//                case '>':
//                case '<=':
//                    return $counted > $count;
//                case '=':
//                case '!=':
//                    return $counted == $count;
//            }
//        });
//
//        // If the operator is <, <= or !=, we will use whereNotIn.
//        $not = in_array($operator, ['<', '<=', '!=']);
//
//        // If we are comparing to 0, we need an additional $not flip.
//        if ($count == 0) {
//            $not = !$not;
//        }
//
//        // All related ids.
//        $relatedIds = array_keys($relationCount);
//
//        // Add whereIn to the query.
//        return $this->whereIn($this->model->getKeyName(), $relatedIds, $boolean, $not);
//    }

    /**
     * Create a raw database expression.
     *
     * @param  closure  $expression
     * @return mixed
     */
    public function raw($expression = null)
    {
        // Get raw results from the query builder.
        $results = $this->query->raw($expression);

        // Convert MongoCursor results to a collection of models.
        if ($results instanceof Cursor) {
            $results = iterator_to_array($results, false);
            return $this->model->hydrate($results);
        }

        // Convert Mongo BSONDocument to a single object.
        elseif ($results instanceof BSONDocument) {
            $results = $results->getArrayCopy();
            return $this->model->newFromBuilder((array) $results);
        }

        // The result is a single object.
        elseif (is_array($results) and array_key_exists('_id', $results)) {
            return $this->model->newFromBuilder((array) $results);
        }

        return $results;
    }
}