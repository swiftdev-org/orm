<?php namespace Swift\ORM;

use CodeIgniter\Model as CodeIgniterModel;
use Swift\ORM\Relations\Relation;
use Swift\ORM\Relations\HasMany;
use Swift\ORM\Relations\BelongsToMany;

/**
 * Eloquent-style Model with relationship eager loading
 */
class Model extends CodeIgniterModel
{
    protected $returnType = Entity::class;
    protected array $with = [];
    protected array $withCount = [];

    /**
     * Set relationships to eager load
     */
    public function with($relations): self
    {
        $clone = clone $this;

        if (is_string($relations)) {
            $relations = [$relations];
        }

        $clone->with = array_merge($clone->with, $relations);
        return $clone;
    }

    /**
     * Set relationships to count
     */
    public function withCount($relations): self
    {
        $clone = clone $this;

        if (is_string($relations)) {
            $relations = [$relations];
        }

        $clone->withCount = array_merge($clone->withCount, $relations);
        return $clone;
    }

    /**
     * Override findAll to support eager loading
     */
    public function findAll(int $limit = 0, int $offset = 0)
    {
        $results = parent::findAll($limit, $offset);

        if (!empty($results)) {
            $results = $this->eagerLoadRelations($results);
            $results = $this->loadRelationCounts($results);
        }

        return $results;
    }

    /**
     * Override find to support eager loading
     */
    public function find($id = null)
    {
        $result = parent::find($id);

        if ($result !== null) {
            if (is_array($result)) {
                $result = $this->eagerLoadRelations($result);
                $result = $this->loadRelationCounts($result);
            } else {
                $result = $this->eagerLoadRelations([$result]);
                $result = $this->loadRelationCounts($result);
                $result = $result[0] ?? null;
            }
        }

        return $result;
    }

    /**
     * Override first to support eager loading
     */
    public function first()
    {
        $result = parent::first();

        if ($result !== null) {
            $result = $this->eagerLoadRelations([$result]);
            $result = $this->loadRelationCounts($result);
            $result = $result[0] ?? null;
        }

        return $result;
    }

    /**
     * Eager load relationships
     */
    protected function eagerLoadRelations(array $models): array
    {
        foreach ($this->with as $name) {
            if (strpos($name, '.') !== false) {
                $models = $this->eagerLoadNestedRelation($models, $name);
            } else {
                $models = $this->eagerLoadRelation($models, $name);
            }
        }

        return $models;
    }

    /**
     * Eager load a single relation
     */
    protected function eagerLoadRelation(array $models, string $relation): array
    {
        // Get the relation from the first model
        if (empty($models)) {
            return $models;
        }

        $firstModel = $models[0];
        if (!method_exists($firstModel, $relation)) {
            return $models;
        }

        // Get relation instance without constraints
        $originalConstraints = Relation::$constraints ?? true;
        Relation::$constraints = false;

        $relationInstance = $firstModel->$relation();

        Relation::$constraints = $originalConstraints;

        if ($relationInstance instanceof Relation) {
            $relationInstance->addEagerConstraints($models);
            $results = $relationInstance->getEager();
            $models = $relationInstance->match($models, $results, $relation);
        }

        return $models;
    }

    /**
     * Eager load nested relation (e.g., 'user.posts')
     */
    protected function eagerLoadNestedRelation(array $models, string $relation): array
    {
        $segments = explode('.', $relation);
        $firstSegment = array_shift($segments);
        $nested = implode('.', $segments);

        $models = $this->eagerLoadRelation($models, $firstSegment);

        // Load nested relations on the related models
        foreach ($models as $model) {
            if ($model->relationLoaded($firstSegment)) {
                $related = $model->{$firstSegment};

                if ($related) {
                    $relatedModels = is_array($related) ? $related : [$related];

                    if (!empty($relatedModels)) {
                        $relatedModel = $relatedModels[0]->getModel();
                        $relatedModel->with = [$nested];
                        $relatedModels = $relatedModel->eagerLoadRelations($relatedModels);

                        if (!is_array($related)) {
                            $model->setRelation($firstSegment, $relatedModels[0]);
                        } else {
                            $model->setRelation($firstSegment, $relatedModels);
                        }
                    }
                }
            }
        }

        return $models;
    }

    /**
     * Load relationship counts
     */
    protected function loadRelationCounts(array $models): array
    {
        foreach ($this->withCount as $relation) {
            if (empty($models)) {
                continue;
            }

            $firstModel = $models[0];
            if (!method_exists($firstModel, $relation)) {
                continue;
            }

            // Get relation instance
            $originalConstraints = Relation::$constraints ?? true;
            Relation::$constraints = false;

            $relationInstance = $firstModel->$relation();

            Relation::$constraints = $originalConstraints;

            if ($relationInstance instanceof HasMany || $relationInstance instanceof BelongsToMany) {
                $this->addRelationCounts($models, $relation, $relationInstance);
            }
        }

        return $models;
    }

    /**
     * Add counts for a relation
     */
    protected function addRelationCounts(array $models, string $relation, Relation $relationInstance): void
    {
        $counts = [];

        foreach ($models as $model) {
            $originalConstraints = Relation::$constraints ?? true;
            Relation::$constraints = false;

            $relationQuery = $model->$relation();

            Relation::$constraints = $originalConstraints;

            if ($relationQuery instanceof Relation) {
                // Get count by loading and counting - in a real implementation,
                // you'd want to do this with actual COUNT queries for efficiency
                $results = $relationQuery->get();
                $counts[$model->id] = count($results);
            }
        }

        foreach ($models as $model) {
            $countAttribute = $relation . '_count';
            $model->attributes[$countAttribute] = $counts[$model->id] ?? 0;
        }
    }
}
