<?php namespace Swift\ORM;

use CodeIgniter\Entity\Entity as CodeIgniterEntity;
use Swift\ORM\Relations\Relation;
use Swift\ORM\Relations\HasOne;
use Swift\ORM\Relations\HasMany;
use Swift\ORM\Relations\BelongsTo;
use Swift\ORM\Relations\BelongsToMany;

/**
 * Eloquent-style Entity with relationship support
 */
class Entity extends CodeIgniterEntity
{
    protected array $relations = [];
    protected array $loaded_relations = [];

    /**
     * Get a relationship or attribute
     */
    public function __get(string $key)
    {
        // Check if it's a loaded relationship
        if (array_key_exists($key, $this->loaded_relations)) {
            return $this->loaded_relations[$key];
        }

        // Check if it's a relationship method
        if (method_exists($this, $key)) {
            return $this->getRelationshipFromMethod($key);
        }

        return parent::__get($key);
    }

    /**
     * Set a relationship result
     */
    public function setRelation(string $name, $value): self
    {
        $this->loaded_relations[$name] = $value;
        return $this;
    }

    /**
     * Check if a relationship is loaded
     */
    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->loaded_relations);
    }

    /**
     * Load a relationship from method
     */
    protected function getRelationshipFromMethod(string $method)
    {
        $relation = $this->$method();

        if ($relation instanceof Relation) {
            $result = $relation->getResults();
            $this->setRelation($method, $result);
            return $result;
        }

        return null;
    }

    // Relationship methods
    protected function hasOne(string $related, string $foreignKey = null, string $localKey = 'id'): HasOne
    {
        $instance = new $related();
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        return new HasOne($instance->getModel(), $this, $related, $foreignKey, $localKey);
    }

    protected function hasMany(string $related, string $foreignKey = null, string $localKey = 'id'): HasMany
    {
        $instance = new $related();
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        return new HasMany($instance->getModel(), $this, $related, $foreignKey, $localKey);
    }

    protected function belongsTo(string $related, string $foreignKey = null, string $ownerKey = 'id'): BelongsTo
    {
        $instance = new $related();
        $foreignKey = $foreignKey ?: $this->getForeignKeyForRelation($related);

        return new BelongsTo($instance->getModel(), $this, $related, $foreignKey, $ownerKey);
    }

    protected function belongsToMany(
        string $related,
        string $table = null,
        string $foreignPivotKey = null,
        string $relatedPivotKey = null,
        string $parentKey = 'id',
        string $relatedKey = 'id'
    ): BelongsToMany {
        $instance = new $related();

        return new BelongsToMany(
            $instance->getModel(),
            $this,
            $related,
            $table ?: $this->joiningTable($related),
            $foreignPivotKey ?: $this->getForeignKey(),
            $relatedPivotKey ?: $instance->getForeignKey(),
            $parentKey,
            $relatedKey
        );
    }

    /**
     * Get the model for this entity
     */
    public function getModel(): Model
    {
        $modelClass = str_replace('Entities', 'Models', get_class($this)) . 'Model';
        return new $modelClass();
    }

    /**
     * Get foreign key for this entity
     */
    protected function getForeignKey(): string
    {
        $class = class_basename(get_class($this));
        return strtolower($class) . '_id';
    }

    /**
     * Get foreign key for a related entity
     */
    protected function getForeignKeyForRelation(string $related): string
    {
        $class = class_basename($related);
        return strtolower($class) . '_id';
    }

    /**
     * Get the joining table name for belongsToMany
     */
    protected function joiningTable(string $related): string
    {
        $models = [
            class_basename(get_class($this)),
            class_basename($related)
        ];

        sort($models);

        return strtolower(implode('_', $models));
    }
}
