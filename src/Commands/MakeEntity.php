<?php namespace Swift\ORM\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Enhanced Entity Generator with Database Analysis
 */
class MakeEntity extends BaseCommand
{
    protected $group = 'Generators';
    protected $name = 'make:entity';
    protected $description = 'Generates a new Swift ORM entity file with database analysis.';
    protected $usage = 'make:entity <n> [options]';
    protected $arguments = [
        'name' => 'The entity class name or table name.'
    ];
    protected $options = [
        '--table' => 'Specify the table name explicitly.',
        '--namespace' => 'Set the namespace (default: App\\Entities).',
        '--all' => 'Generate entities for all tables in the database.',
        '--force' => 'Force overwrite existing files.',
        '--model' => 'Also generate corresponding model file.',
        '--suffix' => 'Add suffix to entity names (e.g., --suffix=Entity).',
    ];

    protected $db;
    protected array $tableCache = [];

    public function run(array $params)
    {
        $this->db = Database::connect();

        if (array_key_exists('all', $params)) {
            $this->generateAllEntities($params);
        } else {
            $name = array_shift($params) ?? CLI::prompt('Entity name');
            if (empty($name)) {
                CLI::error('Entity name is required.');
                return;
            }

            $this->generateSingleEntity($name, $params);
        }
    }

    protected function generateAllEntities(array $params): void
    {
        CLI::write('Analyzing database and generating entities for all tables...', 'yellow');

        $tables = $this->getAllTables();

        foreach ($tables as $table) {
            try {
                $entityName = $this->tableToEntityName($table, $params['suffix'] ?? '');
                CLI::write("Generating entity for table: {$table} -> {$entityName}", 'green');
                $this->generateSingleEntity($entityName, array_merge($params, ['--table' => $table]));
            } catch (\Exception $e) {
                CLI::error("Failed to generate entity for table {$table}: " . $e->getMessage());
            }
        }

        CLI::write('All entities generated successfully!', 'green');
    }

    public function generateSingleEntity(string $name, array $params): ?string
    {
        $namespace = $params['namespace'] ?? 'App\\Entities';
        $tableName = $params['table'] ?? $this->entityNameToTable($name, $params['suffix'] ?? '');
        $force = array_key_exists('force', $params);
        $withModel = array_key_exists('model', $params);
        $suffix = $params['suffix'] ?? '';

        // Add suffix if specified and not already present
        if (!empty($suffix) && !str_ends_with($name, $suffix)) {
            $name .= $suffix;
        }

        // Analyze table structure
        if (!$this->tableExists($tableName)) {
            CLI::error("Table '{$tableName}' does not exist in the database.");
            return null;
        }

        $tableInfo = $this->analyzeTable($tableName);
        $relationships = $this->discoverRelationships($tableName);

        // Generate entity file
        $entityPath = $this->generateEntityFile($name, $namespace, $tableName, $tableInfo, $relationships, $force);

        if ($entityPath) {
            CLI::write("Entity created: {$entityPath}", 'green');

            // Generate model if requested
            if ($withModel) {
                $modelName = $this->entityNameToModelName($name, $suffix);
                $modelNamespace = str_replace('Entities', 'Models', $namespace);

                $modelCommand = new MakeModel();
                $modelParams = [
                    'namespace' => $modelNamespace,
                    'table' => $tableName,
                ];

                if ($force) {
                    $modelParams['force'] = true;
                }

                $modelCommand->generateSingleModel($modelName, $modelParams);
            }
        }

        return $entityPath;
    }

    protected function analyzeTable(string $tableName): array
    {
        if (isset($this->tableCache[$tableName])) {
            return $this->tableCache[$tableName];
        }

        $fields = $this->db->getFieldData($tableName);

        $tableInfo = [
            'fields' => [],
            'primary_key' => 'id',
            'timestamps' => false,
            'soft_deletes' => false,
            'attributes' => [],
            'casts' => [],
        ];

        foreach ($fields as $field) {
            $fieldInfo = [
                'name' => $field->name,
                'type' => $field->type,
                'max_length' => $field->max_length,
                'nullable' => $field->nullable,
                'default' => $field->default,
                'primary' => $field->primary_key,
            ];

            $tableInfo['fields'][$field->name] = $fieldInfo;

            // Determine primary key
            if ($field->primary_key) {
                $tableInfo['primary_key'] = $field->name;
            }

            // Check for timestamps
            if (in_array($field->name, ['created_at', 'updated_at'])) {
                $tableInfo['timestamps'] = true;
            }

            // Check for soft deletes
            if ($field->name === 'deleted_at') {
                $tableInfo['soft_deletes'] = true;
            }

            // Add to attributes (all fields go into attributes)
            $defaultValue = $field->default !== null ? "'{$field->default}'" : 'null';
            $tableInfo['attributes'][$field->name] = $defaultValue;

            // Determine casts
            $cast = $this->getFieldCast($field);
            if ($cast) {
                $tableInfo['casts'][$field->name] = $cast;
            }
        }

        $this->tableCache[$tableName] = $tableInfo;
        return $tableInfo;
    }

    protected function discoverRelationships(string $tableName): array
    {
        $relationships = [
            'belongsTo' => [],
            'hasMany' => [],
            'hasOne' => [],
            'belongsToMany' => [],
        ];

        // Discover belongsTo relationships (foreign keys in current table)
        $foreignKeys = $this->getForeignKeys($tableName);
        foreach ($foreignKeys as $fk) {
            $relatedTable = $fk['referenced_table'];
            $relatedEntity = $this->tableToEntityName($relatedTable);

            $relationName = $this->foreignKeyToRelationName($fk['column_name']);

            $relationships['belongsTo'][] = [
                'name' => $relationName,
                'entity' => $relatedEntity,
                'foreign_key' => $fk['column_name'],
                'owner_key' => $fk['referenced_column'],
            ];
        }

        // Discover hasMany/hasOne relationships (foreign keys pointing to this table)
        $referencingTables = $this->getReferencingTables($tableName);
        foreach ($referencingTables as $ref) {
            $relatedTable = $ref['table'];
            $relatedEntity = $this->tableToEntityName($relatedTable);

            $relationName = $this->tableToRelationName($relatedTable, true); // plural for hasMany

            // Check if it's likely a one-to-one or one-to-many
            $isUnique = $this->isColumnUnique($relatedTable, $ref['column']);
            $relationType = $isUnique ? 'hasOne' : 'hasMany';

            $relationships[$relationType][] = [
                'name' => $relationName,
                'entity' => $relatedEntity,
                'foreign_key' => $ref['column'],
                'local_key' => $ref['referenced_column'],
            ];
        }

        // Discover belongsToMany relationships (through pivot tables)
        $pivotTables = $this->findPivotTables($tableName);
        foreach ($pivotTables as $pivot) {
            $relatedTable = $pivot['related_table'];
            $relatedEntity = $this->tableToEntityName($relatedTable);
            $relationName = $this->tableToRelationName($relatedTable, true);

            $relationships['belongsToMany'][] = [
                'name' => $relationName,
                'entity' => $relatedEntity,
                'pivot_table' => $pivot['pivot_table'],
                'foreign_key' => $pivot['foreign_key'],
                'related_key' => $pivot['related_key'],
                'local_key' => 'id',
                'related_local_key' => 'id',
            ];
        }

        return $relationships;
    }

    protected function generateEntityFile(string $name, string $namespace, string $tableName, array $tableInfo, array $relationships, bool $force): ?string
    {
        $template = $this->getEntityTemplate();

        $replacements = [
            '{namespace}' => $namespace,
            '{class_name}' => $name,
            '{table_name}' => $tableName,
            '{attributes}' => $this->arrayToAssociativeString($tableInfo['attributes']),
            '{casts}' => $this->arrayToAssociativeString($tableInfo['casts']),
            '{relationship_methods}' => $this->generateRelationshipMethods($relationships),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);

        $directory = APPPATH . str_replace('\\', DIRECTORY_SEPARATOR, str_replace('App\\', '', $namespace));
        $filepath = $directory . DIRECTORY_SEPARATOR . $name . '.php';

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($filepath) && !$force) {
            CLI::error("File {$filepath} already exists. Use --force to overwrite.");
            return null;
        }

        file_put_contents($filepath, $content);
        return $filepath;
    }

    protected function generateRelationshipMethods(array $relationships): string
    {
        $methods = [];

        // Generate belongsTo methods
        foreach ($relationships['belongsTo'] as $relation) {
            $methods[] = "    /**\n     * {$relation['name']} relationship\n     */\n    public function {$relation['name']}()\n    {\n        return \$this->belongsTo('App\\Entities\\{$relation['entity']}', '{$relation['foreign_key']}', '{$relation['owner_key']}');\n    }";
        }

        // Generate hasOne methods
        foreach ($relationships['hasOne'] as $relation) {
            $methods[] = "    /**\n     * {$relation['name']} relationship\n     */\n    public function {$relation['name']}()\n    {\n        return \$this->hasOne('App\\Entities\\{$relation['entity']}', '{$relation['foreign_key']}', '{$relation['local_key']}');\n    }";
        }

        // Generate hasMany methods
        foreach ($relationships['hasMany'] as $relation) {
            $methods[] = "    /**\n     * {$relation['name']} relationship\n     */\n    public function {$relation['name']}()\n    {\n        return \$this->hasMany('App\\Entities\\{$relation['entity']}', '{$relation['foreign_key']}', '{$relation['local_key']}');\n    }";
        }

        // Generate belongsToMany methods
        foreach ($relationships['belongsToMany'] as $relation) {
            $methods[] = "    /**\n     * {$relation['name']} relationship\n     */\n    public function {$relation['name']}()\n    {\n        return \$this->belongsToMany('App\\Entities\\{$relation['entity']}', '{$relation['pivot_table']}', '{$relation['foreign_key']}', '{$relation['related_key']}', '{$relation['local_key']}', '{$relation['related_local_key']}');\n    }";
        }

        return implode("\n\n", $methods);
    }

    // Helper methods for name conversion
    protected function entityNameToModelName(string $entityName, string $suffix): string
    {
        // Remove suffix if present to get base name
        if (!empty($suffix) && str_ends_with($entityName, $suffix)) {
            $baseName = substr($entityName, 0, -strlen($suffix));
        } else {
            $baseName = $entityName;
        }

        // Add Model suffix for the model
        return $baseName . 'Model';
    }

    protected function entityNameToTable(string $entityName, string $suffix): string
    {
        // Remove suffix if present
        if (!empty($suffix) && str_ends_with($entityName, $suffix)) {
            $baseName = substr($entityName, 0, -strlen($suffix));
        } else {
            $baseName = $entityName;
        }

        // Convert to snake_case and pluralize
        return $this->pluralize(strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $baseName)));
    }

    protected function tableToEntityName(string $tableName, string $suffix = ''): string
    {
        // Convert to PascalCase and singularize
        $entityName = str_replace(' ', '', ucwords(str_replace('_', ' ', $this->singularize($tableName))));

        // Add suffix if specified
        if (!empty($suffix)) {
            $entityName .= $suffix;
        }

        return $entityName;
    }

    // Database helper methods (shared with MakeModel)
    protected function getAllTables(): array
    {
        return $this->db->listTables();
    }

    protected function tableExists(string $tableName): bool
    {
        return in_array($tableName, $this->getAllTables());
    }

    protected function getForeignKeys(string $tableName): array
    {
        $db = $this->db->database;
        $query = "
            SELECT
                COLUMN_NAME as column_name,
                REFERENCED_TABLE_NAME as referenced_table,
                REFERENCED_COLUMN_NAME as referenced_column
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ";

        return $this->db->query($query, [$db, $tableName])->getResultArray();
    }

    protected function getReferencingTables(string $tableName): array
    {
        $db = $this->db->database;
        $query = "
            SELECT
                TABLE_NAME as `table`,
                COLUMN_NAME as `column`,
                REFERENCED_COLUMN_NAME as referenced_column
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
            AND REFERENCED_TABLE_NAME = ?
        ";

        return $this->db->query($query, [$db, $tableName])->getResultArray();
    }

    protected function findPivotTables(string $tableName): array
    {
        $tables = $this->getAllTables();
        $pivotTables = [];
        $currentTableSingular = $this->singularize($tableName);

        foreach ($tables as $table) {
            // Check if table name suggests it's a pivot table
            if (strpos($table, '_') !== false) {
                $parts = explode('_', $table);
                if (count($parts) === 2) {
                    $table1 = $this->singularize($parts[0]);
                    $table2 = $this->singularize($parts[1]);

                    if ($table1 === $currentTableSingular) {
                        $relatedTable = $this->pluralize($table2);
                        if ($this->tableExists($relatedTable)) {
                            $pivotTables[] = [
                                'pivot_table' => $table,
                                'related_table' => $relatedTable,
                                'foreign_key' => $currentTableSingular . '_id',
                                'related_key' => $table2 . '_id',
                            ];
                        }
                    } elseif ($table2 === $currentTableSingular) {
                        $relatedTable = $this->pluralize($table1);
                        if ($this->tableExists($relatedTable)) {
                            $pivotTables[] = [
                                'pivot_table' => $table,
                                'related_table' => $relatedTable,
                                'foreign_key' => $currentTableSingular . '_id',
                                'related_key' => $table1 . '_id',
                            ];
                        }
                    }
                }
            }
        }

        return $pivotTables;
    }

    protected function getFieldCast($field): ?string
    {
        $type = strtolower($field->type);

        if (in_array($type, ['int', 'integer', 'bigint', 'smallint', 'tinyint'])) {
            return 'integer';
        }

        if (in_array($type, ['decimal', 'float', 'double'])) {
            return 'float';
        }

        if (in_array($type, ['boolean', 'bool', 'tinyint(1)'])) {
            return 'boolean';
        }

        if (in_array($type, ['json', 'jsonb'])) {
            return 'array';
        }

        if (in_array($type, ['datetime', 'timestamp'])) {
            return 'datetime';
        }

        if ($type === 'date') {
            return 'date';
        }

        return null;
    }

    protected function isColumnUnique(string $tableName, string $columnName): bool
    {
        $indexes = $this->getTableIndexes($tableName);

        foreach ($indexes as $index) {
            if ($index['unique'] && count($index['columns']) === 1 && $index['columns'][0] === $columnName) {
                return true;
            }
        }

        return false;
    }

    protected function getTableIndexes(string $tableName): array
    {
        $query = "SHOW INDEX FROM {$tableName}";

        $results = $this->db->query($query)->getResultArray();
        $indexes = [];

        foreach ($results as $row) {
            $keyName = $row['Key_name'];
            if (!isset($indexes[$keyName])) {
                $indexes[$keyName] = [
                    'name' => $keyName,
                    'unique' => !$row['Non_unique'],
                    'columns' => [],
                ];
            }
            $indexes[$keyName]['columns'][] = $row['Column_name'];
        }

        return array_values($indexes);
    }

    // String helper methods
    protected function singularize(string $word): string
    {
        if (str_ends_with($word, 'ies')) {
            return substr($word, 0, -3) . 'y';
        }
        if (str_ends_with($word, 'es')) {
            return substr($word, 0, -2);
        }
        if (str_ends_with($word, 's') && !str_ends_with($word, 'ss')) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    protected function pluralize(string $word): string
    {
        if (str_ends_with($word, 'y')) {
            return substr($word, 0, -1) . 'ies';
        }
        if (str_ends_with($word, ['s', 'sh', 'ch', 'x', 'z'])) {
            return $word . 'es';
        }

        return $word . 's';
    }

    protected function foreignKeyToRelationName(string $foreignKey): string
    {
        return str_replace('_id', '', $foreignKey);
    }

    protected function tableToRelationName(string $tableName, bool $plural = false): string
    {
        $name = $this->singularize($tableName);
        return $plural ? $this->pluralize($name) : $name;
    }

    // Template and formatting methods
    protected function arrayToAssociativeString(array $array): string
    {
        if (empty($array)) {
            return '[]';
        }

        $items = [];
        foreach ($array as $key => $value) {
            // Handle null values and quoted strings
            if ($value === 'null') {
                $items[] = "        '{$key}' => null";
            } elseif (is_string($value) && !str_starts_with($value, "'")) {
                $items[] = "        '{$key}' => '{$value}'";
            } else {
                $items[] = "        '{$key}' => {$value}";
            }
        }

        return "[\n" . implode(",\n", $items) . ",\n    ]";
    }

    protected function getEntityTemplate(): string
    {
        return '<?php

namespace {namespace};

use Swift\ORM\Entity;

/**
 * {class_name} Entity
 *
 * @table {table_name}
 */
class {class_name} extends Entity
{
    protected $attributes = {attributes};

    protected $casts = {casts};

{relationship_methods}
}
';
    }
}
