<?php namespace Swift\ORM\Relations;

use Swift\ORM\Model;
use Swift\ORM\Entity;

/**
 * BelongsTo Relation
 */
class BelongsTo extends Relation
{
    protected string $foreignKey;
    protected string $ownerKey;

    public function __construct(Model $query, Entity $child, string $related, string $foreignKey, string $ownerKey)
    {
        parent::__construct($query, $child, $related);
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
        $this->addConstraints();
    }

    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->ownerKey, $this->parent->{$this->foreignKey});
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->foreignKey);
        $this->query->whereIn($this->ownerKey, $keys);
    }

    public function getResults()
    {
        return $this->first();
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->{$this->foreignKey};
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    protected function buildDictionary(array $results): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->{$this->ownerKey}] = $result;
        }
        return $dictionary;
    }
}
