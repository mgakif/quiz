<?php

declare(strict_types=1);

namespace App\Services\AI;

use RuntimeException;

class SchemaValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public function validate(array $data, string $schemaPath): array
    {
        if (! file_exists($schemaPath)) {
            throw new RuntimeException("Schema file not found: {$schemaPath}");
        }

        $schemaContent = file_get_contents($schemaPath);

        if ($schemaContent === false) {
            throw new RuntimeException("Could not read schema file: {$schemaPath}");
        }

        $schema = json_decode($schemaContent, true);

        if (! is_array($schema)) {
            throw new RuntimeException("Schema file is not valid JSON: {$schemaPath}");
        }

        $errors = [];

        $this->validateNode($data, $schema, '$', $errors, $schema);

        return $errors;
    }

    /**
     * @param  mixed  $value
     * @param  array<string, mixed>  $schema
     * @param  array<int, string>  $errors
     * @param  array<string, mixed>  $rootSchema
     */
    private function validateNode(mixed $value, array $schema, string $path, array &$errors, array $rootSchema): void
    {
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            $resolved = $this->resolveRef($schema['$ref'], $rootSchema);

            if ($resolved === null) {
                $errors[] = "{$path} has unresolved ref {$schema['$ref']}";

                return;
            }

            $this->validateNode($value, $resolved, $path, $errors, $rootSchema);

            return;
        }

        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            foreach ($schema['allOf'] as $index => $branch) {
                if (! is_array($branch)) {
                    continue;
                }

                if (isset($branch['if']) && is_array($branch['if']) && isset($branch['then']) && is_array($branch['then'])) {
                    if ($this->matchesSchema($value, $branch['if'], $rootSchema)) {
                        $this->validateNode($value, $branch['then'], "{$path}.allOf[{$index}]", $errors, $rootSchema);
                    }

                    continue;
                }

                $this->validateNode($value, $branch, "{$path}.allOf[{$index}]", $errors, $rootSchema);
            }
        }

        $type = $schema['type'] ?? null;

        if (is_string($type) && ! $this->matchesType($value, $type)) {
            $errors[] = "{$path} expected type {$type}";

            return;
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[] = "{$path} is not in enum list";
        }

        if (($type === 'string') && isset($schema['minLength']) && is_int($schema['minLength']) && (mb_strlen((string) $value) < $schema['minLength'])) {
            $errors[] = "{$path} minLength is {$schema['minLength']}";
        }

        if (($type === 'string') && isset($schema['pattern']) && is_string($schema['pattern'])) {
            $pattern = '/' . str_replace('/', '\\/', $schema['pattern']) . '/';

            if (preg_match($pattern, (string) $value) !== 1) {
                $errors[] = "{$path} does not match pattern {$schema['pattern']}";
            }
        }

        if (($type === 'number' || $type === 'integer') && (is_int($value) || is_float($value))) {
            if (isset($schema['minimum']) && is_numeric($schema['minimum']) && ($value < $schema['minimum'])) {
                $errors[] = "{$path} minimum is {$schema['minimum']}";
            }

            if (isset($schema['maximum']) && is_numeric($schema['maximum']) && ($value > $schema['maximum'])) {
                $errors[] = "{$path} maximum is {$schema['maximum']}";
            }
        }

        if (($type === 'array') && is_array($value) && array_is_list($value)) {
            if (isset($schema['minItems']) && is_int($schema['minItems']) && (count($value) < $schema['minItems'])) {
                $errors[] = "{$path} minItems is {$schema['minItems']}";
            }

            if (isset($schema['maxItems']) && is_int($schema['maxItems']) && (count($value) > $schema['maxItems'])) {
                $errors[] = "{$path} maxItems is {$schema['maxItems']}";
            }

            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($value as $index => $item) {
                    $this->validateNode($item, $schema['items'], "{$path}[{$index}]", $errors, $rootSchema);
                }
            }
        }

        if (($type === 'object') && is_array($value) && (! array_is_list($value) || ($value === []))) {
            $required = $schema['required'] ?? [];

            if (is_array($required)) {
                foreach ($required as $requiredKey) {
                    if (is_string($requiredKey) && ! array_key_exists($requiredKey, $value)) {
                        $errors[] = "{$path}.{$requiredKey} is required";
                    }
                }
            }

            $properties = $schema['properties'] ?? [];

            if (is_array($properties)) {
                foreach ($properties as $property => $propertySchema) {
                    if (! is_string($property) || ! is_array($propertySchema)) {
                        continue;
                    }

                    if (! array_key_exists($property, $value)) {
                        continue;
                    }

                    $this->validateNode($value[$property], $propertySchema, "{$path}.{$property}", $errors, $rootSchema);
                }
            }

            if (($schema['additionalProperties'] ?? true) === false && is_array($properties)) {
                foreach (array_keys($value) as $key) {
                    if (! array_key_exists((string) $key, $properties)) {
                        $errors[] = "{$path}.{$key} is not allowed";
                    }
                }
            }
        }
    }

    /**
     * @param  mixed  $value
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $rootSchema
     */
    private function matchesSchema(mixed $value, array $schema, array $rootSchema): bool
    {
        $errors = [];

        $this->validateNode($value, $schema, '$tmp', $errors, $rootSchema);

        return $errors === [];
    }

    /**
     * @param  array<string, mixed>  $rootSchema
     * @return array<string, mixed>|null
     */
    private function resolveRef(string $ref, array $rootSchema): ?array
    {
        if (! str_starts_with($ref, '#/')) {
            return null;
        }

        $segments = explode('/', substr($ref, 2));
        $current = $rootSchema;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return is_array($current) ? $current : null;
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'object' => is_array($value) && (! array_is_list($value) || ($value === [])),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            default => false,
        };
    }
}
