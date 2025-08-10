<?php namespace Swift\ORM\Relations;

use Swift\ORM\Model;
use Swift\ORM\Entity;

/**
 * BelongsToMany Relation
 */
class BelongsToMany extends Relation
{
    protected string $table;
    protected string $foreignPivotKey;
    protected string $relatedPivotKey;
    protected string $parentKey;
    protected string $relatedKey;

    public function __construct(
        Model $query,
        Entity $parent,
        string $related,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey
    ) {
        parent::__construct($query, $parent, $related);
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
    }

    public function addConstraints(): void
    {
        // Constraints added in getResults for pivot queries
    }

    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->parentKey);
        $this->query->join($this->table, $this->table . '.' . $this->relatedPivotKey . ' = ' . $this->query->getTable() . '.' . $this->relatedKey)
                   ->whereIn($this->table . '.' . $this->foreignPivotKey, $keys);
    }

    public function getResults()
    {
        return $this->query
            ->join($this->table, $this->table . '.' . $this->relatedPivotKey . ' = ' . $this->query->getTable() . '.' . $this->relatedKey)
            ->where($this->table . '.' . $this->foreignPivotKey, $this->parent->{$this->parentKey})
            ->findAll();
    }

    public function match(array $models, array $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->{$this->parentKey};
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            } else {
                $model->setRelation($relation, []);
            }
        }

        return $models;
    }

    protected function buildDictionary(array $results): array
    {
        $dictionary = [];

        // We need to get pivot data for many-to-many
        $db = \Config\Database::connect();
        $pivotData = $db->table($this->table)->get()->getResultArray();

        $pivotMap = [];
        foreach ($pivotData as $pivot) {
            $pivotMap[$pivot[$this->relatedPivotKey]][] = $pivot[$this->foreignPivotKey];
        }

        foreach ($results as $result) {
            $relatedId = $result->{$this->relatedKey};
            if (isset($pivotMap[$relatedId])) {
                foreach ($pivotMap[$relatedId] as $parentId) {
                    $dictionary[$parentId][] = $result;
                }
            }
        }

        return $dictionary;
    }
}
