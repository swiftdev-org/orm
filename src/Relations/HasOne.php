<?php namespace Swift\ORM\Relations;

use Swift\ORM\Model;
use Swift\ORM\Entity;

/**
 * HasOne Relation
 */
class HasOne extends Relation
{
    protected string $foreignKey;
    protected string $localKey;

    public function __construct(Model $query, Entity $parent, string $related, string $foreignKey, string $localKey)
    {
        parent::__construct($query, $parent, $related);
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        $this->addConstraints();
    }

    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->foreignKey, $this->parent->{$this->localKey});
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->localKey);
        $this->query->whereIn($this->foreignKey, $keys);
    }

    public function getResults()
    {
        return $this->first();
    }

    public function match(array $models, array $results, string $relation): array
    {
        return $this->matchOne($models, $results, $relation);
    }

    protected function matchOne(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->{$this->localKey};
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
            $dictionary[$result->{$this->foreignKey}] = $result;
        }
        return $dictionary;
    }
}
