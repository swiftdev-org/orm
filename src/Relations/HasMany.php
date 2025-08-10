<?php namespace Swift\ORM\Relations;

/**
 * HasMany Relation
 */
class HasMany extends HasOne
{
    public function getResults()
    {
        return $this->get();
    }

    public function match(array $models, array $results, string $relation): array
    {
        return $this->matchMany($models, $results, $relation);
    }

    protected function matchMany(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionaryMany($results);

        foreach ($models as $model) {
            $key = $model->{$this->localKey};
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            } else {
                $model->setRelation($relation, []);
            }
        }

        return $models;
    }

    protected function buildDictionaryMany(array $results): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->{$this->foreignKey}][] = $result;
        }
        return $dictionary;
    }
}
