<?php

namespace Tools\Audit\Analyzers;

/**
 * PrismaSchemaParser
 * 
 * Parses Prisma schema files to extract models, fields, relations, indexes, and mappings.
 * Handles @@map and @map directives for table and column name mappings.
 * 
 * Requirements: 2.1, 2.2, 2.3
 */
class PrismaSchemaParser
{
    private $schemaPath;
    private $models = [];
    private $enums = [];
    private $errors = [];

    /**
     * Constructor
     * 
     * @param string $schemaPath Path to prisma/schema.prisma file
     */
    public function __construct($schemaPath)
    {
        $this->schemaPath = $schemaPath;
    }

    /**
     * Parse the Prisma schema file
     * 
     * @return array Parsed schema structure
     */
    public function parse()
    {
        if (!file_exists($this->schemaPath)) {
            $this->errors[] = "Schema file not found: {$this->schemaPath}";
            return [
                'success' => false,
                'errors' => $this->errors,
                'models' => [],
                'enums' => []
            ];
        }

        $content = file_get_contents($this->schemaPath);
        
        // Parse models
        $this->parseModels($content);
        
        // Parse enums
        $this->parseEnums($content);

        return [
            'success' => count($this->errors) === 0,
            'errors' => $this->errors,
            'models' => $this->models,
            'enums' => $this->enums
        ];
    }

    /**
     * Parse model definitions from schema content
     * 
     * @param string $content Schema file content
     */
    private function parseModels($content)
    {
        // Match model blocks: model ModelName { ... }
        preg_match_all('/model\s+(\w+)\s*\{([^}]+)\}/s', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $modelName = $match[1];
            $modelBody = $match[2];

            $model = [
                'name' => $modelName,
                'tableName' => $this->toSnakeCase($modelName), // Default table name
                'fields' => [],
                'indexes' => [],
                'relations' => [],
                'uniqueConstraints' => []
            ];

            // Parse fields
            $this->parseFields($modelBody, $model);

            // Parse @@map directive for table name
            if (preg_match('/@@map\s*\(\s*"([^"]+)"\s*\)/', $modelBody, $mapMatch)) {
                $model['tableName'] = $mapMatch[1];
            }

            // Parse @@index directives
            $this->parseIndexes($modelBody, $model);

            // Parse @@unique directives
            $this->parseUniqueConstraints($modelBody, $model);

            $this->models[$modelName] = $model;
        }
    }

    /**
     * Parse field definitions from model body
     * 
     * @param string $modelBody Model body content
     * @param array &$model Model array to populate
     */
    private function parseFields($modelBody, &$model)
    {
        // Split into lines
        $lines = explode("\n", $modelBody);

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || strpos($line, '//') === 0 || strpos($line, '@@') === 0) {
                continue;
            }

            // Match field definition: fieldName Type modifiers @attributes
            if (preg_match('/^(\w+)\s+(\w+)(\?|\[\])?(.*)$/', $line, $fieldMatch)) {
                $fieldName = $fieldMatch[1];
                $fieldType = $fieldMatch[2];
                $modifier = $fieldMatch[3] ?? '';
                $attributes = $fieldMatch[4] ?? '';

                $field = [
                    'name' => $fieldName,
                    'columnName' => $this->toSnakeCase($fieldName), // Default column name
                    'type' => $fieldType,
                    'nullable' => $modifier === '?',
                    'array' => $modifier === '[]',
                    'default' => null,
                    'unique' => false,
                    'primaryKey' => false,
                    'autoIncrement' => false,
                    'relation' => null
                ];

                // Parse @map directive for column name
                if (preg_match('/@map\s*\(\s*"([^"]+)"\s*\)/', $attributes, $mapMatch)) {
                    $field['columnName'] = $mapMatch[1];
                }

                // Parse @default directive
                if (preg_match('/@default\s*\(([^)]+)\)/', $attributes, $defaultMatch)) {
                    $field['default'] = trim($defaultMatch[1]);
                    
                    // Check for autoincrement
                    if (strpos($field['default'], 'autoincrement') !== false) {
                        $field['autoIncrement'] = true;
                    }
                }

                // Parse @id directive
                if (strpos($attributes, '@id') !== false) {
                    $field['primaryKey'] = true;
                }

                // Parse @unique directive
                if (strpos($attributes, '@unique') !== false) {
                    $field['unique'] = true;
                }

                // Parse @relation directive
                if (preg_match('/@relation\s*\(([^)]+)\)/', $attributes, $relationMatch)) {
                    $field['relation'] = $this->parseRelation($relationMatch[1]);
                }

                $model['fields'][$fieldName] = $field;
            }
        }
    }

    /**
     * Parse relation attributes
     * 
     * @param string $relationStr Relation attribute content
     * @return array Parsed relation data
     */
    private function parseRelation($relationStr)
    {
        $relation = [
            'fields' => [],
            'references' => [],
            'onDelete' => null,
            'onUpdate' => null
        ];

        // Parse fields
        if (preg_match('/fields:\s*\[([^\]]+)\]/', $relationStr, $fieldsMatch)) {
            $relation['fields'] = array_map('trim', explode(',', $fieldsMatch[1]));
        }

        // Parse references
        if (preg_match('/references:\s*\[([^\]]+)\]/', $relationStr, $referencesMatch)) {
            $relation['references'] = array_map('trim', explode(',', $referencesMatch[1]));
        }

        // Parse onDelete
        if (preg_match('/onDelete:\s*(\w+)/', $relationStr, $onDeleteMatch)) {
            $relation['onDelete'] = $onDeleteMatch[1];
        }

        // Parse onUpdate
        if (preg_match('/onUpdate:\s*(\w+)/', $relationStr, $onUpdateMatch)) {
            $relation['onUpdate'] = $onUpdateMatch[1];
        }

        return $relation;
    }

    /**
     * Parse @@index directives
     * 
     * @param string $modelBody Model body content
     * @param array &$model Model array to populate
     */
    private function parseIndexes($modelBody, &$model)
    {
        // Match @@index([fields])
        preg_match_all('/@@index\s*\(\s*\[([^\]]+)\]([^)]*)\)/', $modelBody, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $fields = array_map('trim', explode(',', $match[1]));
            $attributes = $match[2] ?? '';

            $index = [
                'fields' => $fields,
                'name' => null,
                'unique' => false
            ];

            // Parse name
            if (preg_match('/name:\s*"([^"]+)"/', $attributes, $nameMatch)) {
                $index['name'] = $nameMatch[1];
            }

            $model['indexes'][] = $index;
        }

        // Also parse @@id for composite primary keys
        if (preg_match('/@@id\s*\(\s*\[([^\]]+)\]/', $modelBody, $idMatch)) {
            $fields = array_map('trim', explode(',', $idMatch[1]));
            $model['indexes'][] = [
                'fields' => $fields,
                'name' => 'PRIMARY',
                'unique' => true,
                'primary' => true
            ];
        }
    }

    /**
     * Parse @@unique directives
     * 
     * @param string $modelBody Model body content
     * @param array &$model Model array to populate
     */
    private function parseUniqueConstraints($modelBody, &$model)
    {
        // Match @@unique([fields])
        preg_match_all('/@@unique\s*\(\s*\[([^\]]+)\]([^)]*)\)/', $modelBody, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $fields = array_map('trim', explode(',', $match[1]));
            $attributes = $match[2] ?? '';

            $unique = [
                'fields' => $fields,
                'name' => null
            ];

            // Parse name
            if (preg_match('/name:\s*"([^"]+)"/', $attributes, $nameMatch)) {
                $unique['name'] = $nameMatch[1];
            }

            $model['uniqueConstraints'][] = $unique;
        }
    }

    /**
     * Parse enum definitions from schema content
     * 
     * @param string $content Schema file content
     */
    private function parseEnums($content)
    {
        // Match enum blocks: enum EnumName { ... }
        preg_match_all('/enum\s+(\w+)\s*\{([^}]+)\}/s', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $enumName = $match[1];
            $enumBody = $match[2];

            $values = [];
            $lines = explode("\n", $enumBody);
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '//') !== 0) {
                    $values[] = $line;
                }
            }

            $this->enums[$enumName] = [
                'name' => $enumName,
                'values' => $values
            ];
        }
    }

    /**
     * Convert PascalCase/camelCase to snake_case
     * 
     * @param string $str Input string
     * @return string snake_case string
     */
    private function toSnakeCase($str)
    {
        // Insert underscore before uppercase letters and convert to lowercase
        $snake = preg_replace('/([a-z])([A-Z])/', '$1_$2', $str);
        return strtolower($snake);
    }

    /**
     * Get parsed models
     * 
     * @return array Models array
     */
    public function getModels()
    {
        return $this->models;
    }

    /**
     * Get parsed enums
     * 
     * @return array Enums array
     */
    public function getEnums()
    {
        return $this->enums;
    }

    /**
     * Get parsing errors
     * 
     * @return array Errors array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get model by name
     * 
     * @param string $modelName Model name
     * @return array|null Model data or null if not found
     */
    public function getModel($modelName)
    {
        return $this->models[$modelName] ?? null;
    }

    /**
     * Get table name for a model
     * 
     * @param string $modelName Model name
     * @return string|null Table name or null if model not found
     */
    public function getTableName($modelName)
    {
        $model = $this->getModel($modelName);
        return $model ? $model['tableName'] : null;
    }

    /**
     * Get column name for a field
     * 
     * @param string $modelName Model name
     * @param string $fieldName Field name
     * @return string|null Column name or null if not found
     */
    public function getColumnName($modelName, $fieldName)
    {
        $model = $this->getModel($modelName);
        if (!$model || !isset($model['fields'][$fieldName])) {
            return null;
        }
        return $model['fields'][$fieldName]['columnName'];
    }
}
