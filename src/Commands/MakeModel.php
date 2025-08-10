<?php

namespace Swift\ORM\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Enhanced Model Generator with Database Analysis
 */
class MakeModel extends BaseCommand
{
    protected $group = 'Generators';
    protected $name = 'make:model';
    protected $description = 'Generates a new Swift ORM model file with database analysis.';
    protected $usage = 'make:model <name> [options]';
    protected $arguments = [
        'name' => 'The model class name or table name.'
    ];
    protected $options = [
        '--table' => 'Specify the table name explicitly.',
        '--namespace' => 'Set the namespace (default: App\\Models).',
        '--all' => 'Generate models for all tables in the database.',
        '--force' => 'Force overwrite existing files.',
        '--entity' => 'Also generate corresponding entity file.',
        '--suffix' => 'Add suffix to model names (e.g., --suffix=Model).',
    ];

    protected $db;
    protected array $tableCache = [];

    public function run(array $params)
    {
        $this->db = Database::connect();

        if (array_key_exists('all', $params)) {
            $this->generateAllModels($params);
        } else {
            $name = array_shift($params) ?? CLI::prompt('Model name');
            if (empty($name)) {
                CLI::error('Model name is required.');
                return;
            }

            $this->generateSingleModel($name, $params);
        }
    }

    protected function generateAllModels(array $params): void
    {
        CLI::write('Analyzing database and generating models for all tables...', 'yellow');

        $tables = $this->getAllTables();

        foreach ($tables as $table) {
            try {
                $modelName = $this->tableToModelName($table, $params['suffix'] ?? '');
                CLI::write("Generating model for table: {$table} -> {$modelName}", 'green');
                $this->generateSingleModel($modelName, array_merge($params, ['--table' => $table]));
            } catch (\Exception $e) {
                CLI::error("Failed to generate model for table {$table}: " . $e->getMessage());
            }
        }

        CLI::write('All models generated successfully!', 'green');
    }

    public function generateSingleModel(string $name, array $params): void
    {
        $namespace = $params['namespace'] ?? 'App\\Models';
        $tableName = $params['table'] ?? $this->modelNameToTable($name, $params['suffix'] ?? '');
        $force = array_key_exists('force', $params);
        $withEntity = array_key_exists('entity', $params);
        $suffix = $params['suffix'] ?? '';

        // Add suffix if specified and not already present
        if (!empty($suffix) && !str_ends_with($name, $suffix)) {
            $name .= $suffix;
        }

        // Analyze table structure
        if (!$this->tableExists($tableName)) {
            CLI::error("Table '{$tableName}' does not exist in the database.");
            return;
        }

        $tableInfo = $this->analyzeTable($tableName);
        $relationships = $this->discoverRelationships($tableName);

        // Generate model file
        $modelPath = $this->generateModelFile($name, $namespace, $tableName, $tableInfo, $relationships, $force);

        if ($modelPath) {
            CLI::write("Model created: {$modelPath}", 'green');

            // Generate entity if requested
            if ($withEntity) {
                $entityName = $this->modelNameToEntityName($name, $suffix);
                $entityNamespace = str_replace('Models', 'Entities', $namespace);

                $entityCommand = new MakeEntity();
                $entityParams = [
                    'namespace' => $entityNamespace,
                    'table' => $tableName,
                ];

                if ($force) {
                    $entityParams['force'] = true;
                }

                $entityCommand->generateSingleEntity($entityName, $entityParams);
            }
        }
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
            'fillable' => [],
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

            // Determine fillable fields (exclude primary key and timestamps)
            if (!$field->primary_key && !in_array($field->name, ['created_at', 'updated_at', 'deleted_at'])) {
                $tableInfo['fillable'][] = $field->name;
            }

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

    protected function generateModelFile(string $name, string $namespace, string $tableName, array $tableInfo, array $relationships, bool $force): ?string
    {
        $template = $this->getModelTemplate();
        $entityName = $this->modelNameToEntityName($name, '');
        $entityNamespace = str_replace('Models', 'Entities', $namespace);

        $replacements = [
            '{namespace}' => $namespace,
            '{class_name}' => $name,
            '{table_name}' => $tableName,
            '{primary_key}' => $tableInfo['primary_key'],
            '{entity_class}' => $entityNamespace . '\\' . $entityName,
            '{allowed_fields}' => $this->arrayToString($tableInfo['fillable']),
            '{casts}' => $this->arrayToAssociativeString($tableInfo['casts']),
            '{use_timestamps}' => $tableInfo['timestamps'] ? 'true' : 'false',
            '{use_soft_deletes}' => $tableInfo['soft_deletes'] ? 'true' : 'false',
            '{validation_rules}' => $this->generateValidationRules($tableInfo['fields']),
            '{scopes}' => $this->generateScopes($tableName, $relationships),
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

    // Helper methods for name conversion
    protected function modelNameToEntityName(string $modelName, string $suffix): string
    {
        // If a suffix was used, remove it to get the entity name
        if (!empty($suffix) && str_ends_with($modelName, $suffix)) {
            return substr($modelName, 0, -strlen($suffix));
        }

        // If no suffix or suffix not found, return as-is
        return $modelName;
    }

    protected function modelNameToTable(string $modelName, string $suffix): string
    {
        // Remove suffix if present
        $baseName = $this->modelNameToEntityName($modelName, $suffix);

        // Convert to snake_case and pluralize
        return $this->pluralize(strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $baseName)));
    }

    protected function tableToModelName(string $tableName, string $suffix = ''): string
    {
        // Convert to PascalCase and singularize
        $modelName = str_replace(' ', '', ucwords(str_replace('_', ' ', $this->singularize($tableName))));

        // Add suffix if specified
        if (!empty($suffix)) {
            $modelName .= $suffix;
        }

        return $modelName;
    }

    protected function tableToEntityName(string $tableName): string
    {
        // Convert to PascalCase and singularize (no suffix for entities)
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $this->singularize($tableName))));
    }

    // Helper methods for database analysis
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
        // Simple singularization
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
        // Simple pluralization
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
        // Convert user_id to user
        return str_replace('_id', '', $foreignKey);
    }

    protected function tableToRelationName(string $tableName, bool $plural = false): string
    {
        $name = $this->singularize($tableName);
        return $plural ? $this->pluralize($name) : $name;
    }

    // Template and generation methods
    protected function arrayToString(array $array): string
    {
        if (empty($array)) {
            return '[]';
        }

        $items = array_map(fn($item) => "        '{$item}'", $array);
        return "[\n" . implode(",\n", $items) . ",\n    ]";
    }

    protected function arrayToAssociativeString(array $array): string
    {
        if (empty($array)) {
            return '[]';
        }

        $items = [];
        foreach ($array as $key => $value) {
            $items[] = "        '{$key}' => '{$value}'";
        }

        return "[\n" . implode(",\n", $items) . ",\n    ]";
    }

    protected function generateValidationRules(array $fields): string
    {
        $rules = [];

        foreach ($fields as $field) {
            if ($field['primary']) {
                continue; // Skip primary key
            }

            $fieldRules = [];

            if (!$field['nullable']) {
                $fieldRules[] = 'required';
            }

            if ($field['type'] === 'varchar' && $field['max_length']) {
                $fieldRules[] = "max_length[{$field['max_length']}]";
            }

            if (strpos($field['name'], 'email') !== false) {
                $fieldRules[] = 'valid_email';
            }

            if (!empty($fieldRules)) {
                $rules[] = "        '{$field['name']}' => '" . implode('|', $fieldRules) . "'";
            }
        }

        if (empty($rules)) {
            return '[]';
        }

        return "[\n" . implode(",\n", $rules) . ",\n    ]";
    }

    protected function generateScopes(string $tableName, array $relationships): string
    {
        // Generate some basic scope methods
        $scopes = [];

        // Add active scope if there's a status or active field
        $tableInfo = $this->analyzeTable($tableName);
        if (isset($tableInfo['fields']['active'])) {
            $scopes[] = "    /**\n     * Scope to get active records\n     */\n    public function active()\n    {\n        return \$this->where('active', 1);\n    }";
        }

        if (isset($tableInfo['fields']['status'])) {
            $scopes[] = "    /**\n     * Scope to get by status\n     */\n    public function byStatus(\$status)\n    {\n        return \$this->where('status', \$status);\n    }";
        }

        return implode("\n\n", $scopes);
    }

    protected function getModelTemplate(): string
    {
        return '<?php

namespace {namespace};

use Swift\ORM\Model;

/**
 * {class_name} Model
 *
 * @table {table_name}
 */
class {class_name} extends Model
{
    protected $table = \'{table_name}\';
    protected $primaryKey = \'{primary_key}\';
    protected $returnType = \'{entity_class}\';

    protected $allowedFields = {allowed_fields};

    protected $useTimestamps = {use_timestamps};
    protected $createdField = \'created_at\';
    protected $updatedField = \'updated_at\';
    protected $useSoftDeletes = {use_soft_deletes};
    protected $deletedField = \'deleted_at\';

    protected $validationRules = {validation_rules};

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

{scopes}
}
';
    }
}
