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
        $modelClass = str_replace('Entity', 'Model', str_replace('Entities', 'Models', get_class($this)));
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

    /**
     * Override toArray to include loaded relations
     *
     * Compatible with CodeIgniter\Entity\Entity::toArray signature
     *
     * @param bool $onlyChanged If true, only return values that have changed since object creation
     * @param bool $cast        If true, cast values according to the $casts property
     * @param bool $recursive   If true, include relationships in the array output
     * @return array
     */
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        // Get base array from parent
        $array = parent::toArray($onlyChanged, $cast, false);

        // Add loaded relations if recursive is true
        if ($recursive) {
            foreach ($this->loaded_relations as $relationName => $relationData) {
                $array[$relationName] = $this->relationToArray($relationData, $onlyChanged, $cast, $recursive);
            }
        }

        return $array;
    }

    /**
     * Convert relationship data to array format
     *
     * @param mixed $relationData
     * @param bool $onlyChanged
     * @param bool $cast
     * @param bool $recursive
     * @return mixed
     */
    protected function relationToArray($relationData, bool $onlyChanged = false, bool $cast = true, bool $recursive = true)
    {
        if ($relationData === null) {
            return null;
        }

        // Handle single entity
        if ($relationData instanceof Entity) {
            return $relationData->toArray($onlyChanged, $cast, $recursive);
        }

        // Handle array of entities
        if (is_array($relationData)) {
            $result = [];
            foreach ($relationData as $item) {
                if ($item instanceof Entity) {
                    $result[] = $item->toArray($onlyChanged, $cast, $recursive);
                } else {
                    $result[] = $item;
                }
            }
            return $result;
        }

        // Handle other types (primitives, objects, etc.)
        return $relationData;
    }

    /**
     * Convert to array without relations (flat array)
     *
     * @param bool $onlyChanged If true, only return values that have changed since object creation
     * @param bool $cast        If true, cast values according to the $casts property
     * @return array
     */
    public function toFlatArray(bool $onlyChanged = false, bool $cast = true): array
    {
        return parent::toArray($onlyChanged, $cast, false);
    }

    /**
     * Convert to array including only specific relations
     *
     * @param array $relations Array of relation names to include
     * @param bool $onlyChanged If true, only return values that have changed since object creation
     * @param bool $cast        If true, cast values according to the $casts property
     * @param bool $recursive   If true, include nested relationships
     * @return array
     */
    public function toArrayWith(array $relations, bool $onlyChanged = false, bool $cast = true, bool $recursive = true): array
    {
        // Get base array
        $array = parent::toArray($onlyChanged, $cast, false);

        // Add only specified relations
        foreach ($relations as $relationName) {
            if (isset($this->loaded_relations[$relationName])) {
                $array[$relationName] = $this->relationToArray(
                    $this->loaded_relations[$relationName],
                    $onlyChanged,
                    $cast,
                    $recursive
                );
            }
        }

        return $array;
    }

    /**
     * Convert to array excluding specific relations
     *
     * @param array $relations Array of relation names to exclude
     * @param bool $onlyChanged If true, only return values that have changed since object creation
     * @param bool $cast        If true, cast values according to the $casts property
     * @param bool $recursive   If true, include nested relationships
     * @return array
     */
    public function toArrayExcept(array $relations, bool $onlyChanged = false, bool $cast = true, bool $recursive = true): array
    {
        // Get base array
        $array = parent::toArray($onlyChanged, $cast, false);

        // Add all loaded relations except specified ones
        foreach ($this->loaded_relations as $relationName => $relationData) {
            if (!in_array($relationName, $relations)) {
                $array[$relationName] = $this->relationToArray(
                    $relationData,
                    $onlyChanged,
                    $cast,
                    $recursive
                );
            }
        }

        return $array;
    }

    /**
     * Convert to JSON including loaded relations
     *
     * @param bool $onlyChanged If true, only return values that have changed since object creation
     * @param bool $cast        If true, cast values according to the $casts property
     * @param bool $recursive   If true, include relationships in the JSON output
     * @return string
     */
    public function toJson(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): string
    {
        return json_encode($this->toArray($onlyChanged, $cast, $recursive), JSON_PRETTY_PRINT);
    }

    /**
     * Get loaded relations as array
     *
     * @return array
     */
    public function getLoadedRelations(): array
    {
        return array_keys($this->loaded_relations);
    }

    /**
     * Get specific loaded relation as array
     *
     * @param string $relationName
     * @param bool $onlyChanged
     * @param bool $cast
     * @param bool $recursive
     * @return mixed|null
     */
    public function getLoadedRelation(string $relationName, bool $onlyChanged = false, bool $cast = true, bool $recursive = true)
    {
        if (!isset($this->loaded_relations[$relationName])) {
            return null;
        }

        return $this->relationToArray($this->loaded_relations[$relationName], $onlyChanged, $cast, $recursive);
    }

    /**
     * Check if entity has any loaded relations
     *
     * @return bool
     */
    public function hasLoadedRelations(): bool
    {
        return !empty($this->loaded_relations);
    }

}
