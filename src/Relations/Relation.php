<?php namespace Swift\ORM\Relations;

use Swift\ORM\Model;
use Swift\ORM\Entity;

/**
 * Abstract Relation class
 */
abstract class Relation
{
    protected Model $query;
    protected Entity $parent;
    protected string $related;
    protected static bool $constraints = true;

    public function __construct(Model $query, Entity $parent, string $related)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $related;
    }

    abstract public function getResults();
    abstract public function addEagerConstraints(array $models): void;
    abstract public function match(array $models, array $results, string $relation): array;

    /**
     * Add constraints for eager loading
     */
    public function addConstraints(): void
    {
        // Default implementation - override in subclasses
    }

    /**
     * Set the constraints for an eager load of the relation
     */
    public function getEager(): array
    {
        return $this->get();
    }

    /**
     * Execute the query and get the results
     */
    public function get(): array
    {
        return $this->query->findAll();
    }

    /**
     * Get the first result
     */
    public function first()
    {
        return $this->query->first();
    }

    /**
     * Helper method to get keys from models
     */
    protected function getKeys(array $models, string $key): array
    {
        $keys = [];
        foreach ($models as $model) {
            $value = $model->{$key};
            if ($value !== null) {
                $keys[] = $value;
            }
        }
        return array_values(array_unique($keys));
    }
}
