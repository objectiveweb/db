<?php

declare(strict_types=1);

namespace Objectiveweb\DB;

use Objectiveweb\DB\Exception\ModelValidationException;

class Model implements \JsonSerializable
{
    /** @var list<string> */
    protected static array $validFields = [];

    /** @var array<string,array<string,mixed>> */
    protected static array $creationRules = [];

    /** @var array<string,array<string,mixed>> */
    protected static array $updateRules = [];

    /** @var array<string,mixed> */
    protected array $attributes = [];

    /** @param array<string,mixed> $data */
    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public static function normalizeForCreate(array $data): array
    {
        $filtered = static::filterValidFields($data);
        $filtered = static::applyRules($filtered, static::$creationRules, true);

        if ($filtered === []) {
            throw new ModelValidationException(static::class . ' has no valid fields for creation');
        }

        return $filtered;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public static function normalizeForUpdate(array $data): array
    {
        $filtered = static::filterValidFields($data);
        $rules = static::$updateRules !== [] ? static::$updateRules : static::$creationRules;
        $filtered = static::applyRules($filtered, $rules, false);

        if ($filtered === []) {
            throw new ModelValidationException(static::class . ' has no valid fields for update');
        }

        return $filtered;
    }

    /** @param array<string,mixed> $data */
    public function fill(array $data): self
    {
        foreach (static::filterValidFields($data) as $key => $value) {
            $this->attributes[$key] = $value;

            if (!property_exists($this, $key)) {
                continue;
            }

            $property = new \ReflectionProperty($this, $key);
            if (!$property->isPublic()) {
                continue;
            }

            try {
                $this->{$key} = $value;
            } catch (\TypeError) {
                // Keep raw value in attributes even when public typed property rejects it.
            }
        }

        return $this;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    protected static function filterValidFields(array $data): array
    {
        $validFields = static::$validFields;
        if ($validFields === []) {
            return $data;
        }

        return array_intersect_key($data, array_flip($validFields));
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,array<string,mixed>> $rules
     * @return array<string,mixed>
     */
    protected static function applyRules(array $data, array $rules, bool $isCreate): array
    {
        $normalized = $data;

        foreach ($rules as $field => $fieldRules) {
            $exists = array_key_exists($field, $normalized);

            if (!$exists) {
                if ($isCreate && (($fieldRules['required'] ?? false) === true)) {
                    throw new ModelValidationException("{$field} is required");
                }

                continue;
            }

            $value = $normalized[$field];

            if ($value === null) {
                $nullable = ($fieldRules['nullable'] ?? false) === true;
                if (!$nullable && (($fieldRules['required'] ?? false) === true)) {
                    throw new ModelValidationException("{$field} cannot be null");
                }

                continue;
            }

            if (isset($fieldRules['filter'])) {
                $value = static::applyPhpFilter($field, $value, $fieldRules);
            }

            if (isset($fieldRules['validate'])) {
                static::runCustomValidator($field, $value, $normalized, $fieldRules['validate'], $isCreate);
            }

            if (isset($fieldRules['type']) && !static::isOfType($value, (string) $fieldRules['type'])) {
                throw new ModelValidationException("{$field} must be of type {$fieldRules['type']}");
            }

            if (isset($fieldRules['enum']) && is_array($fieldRules['enum']) && !in_array($value, $fieldRules['enum'], true)) {
                throw new ModelValidationException("{$field} has an invalid value");
            }

            if (isset($fieldRules['pattern']) && is_string($fieldRules['pattern']) && is_string($value)) {
                if (preg_match($fieldRules['pattern'], $value) !== 1) {
                    throw new ModelValidationException("{$field} format is invalid");
                }
            }

            if (isset($fieldRules['min'])) {
                static::assertMin($field, $value, $fieldRules['min']);
            }

            if (isset($fieldRules['max'])) {
                static::assertMax($field, $value, $fieldRules['max']);
            }

            $normalized[$field] = $value;
        }

        return $normalized;
    }

    private static function isOfType(mixed $value, string $type): bool
    {
        return match ($type) {
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value),
            'numeric' => is_numeric($value),
            'string' => is_string($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            default => true,
        };
    }

    private static function assertMin(string $field, mixed $value, mixed $min): void
    {
        if (is_numeric($value) && $value < $min) {
            throw new ModelValidationException("{$field} must be >= {$min}");
        }

        if (is_string($value) && strlen($value) < (int) $min) {
            throw new ModelValidationException("{$field} length must be >= {$min}");
        }
    }

    private static function assertMax(string $field, mixed $value, mixed $max): void
    {
        if (is_numeric($value) && $value > $max) {
            throw new ModelValidationException("{$field} must be <= {$max}");
        }

        if (is_string($value) && strlen($value) > (int) $max) {
            throw new ModelValidationException("{$field} length must be <= {$max}");
        }
    }

    /** @param array<string,mixed> $fieldRules */
    private static function applyPhpFilter(string $field, mixed $value, array $fieldRules): mixed
    {
        $filter = $fieldRules['filter'];
        if (!is_int($filter)) {
            throw new ModelValidationException("{$field} has an invalid filter definition");
        }

        $options = [];

        if (array_key_exists('filter_options', $fieldRules)) {
            $options['options'] = $fieldRules['filter_options'];
        }

        if (array_key_exists('filter_flags', $fieldRules)) {
            $options['flags'] = $fieldRules['filter_flags'];
        }

        $flags = (int) ($options['flags'] ?? 0);
        $options['flags'] = $flags | FILTER_NULL_ON_FAILURE;

        $filtered = filter_var($value, $filter, $options);
        if ($filtered === null || $filtered === false) {
            throw new ModelValidationException("{$field} failed filter validation");
        }

        return $filtered;
    }

    /** @param array<string,mixed> $data */
    private static function runCustomValidator(
        string $field,
        mixed $value,
        array $data,
        mixed $validator,
        bool $isCreate
    ): void {
        if (!is_callable($validator)) {
            throw new ModelValidationException("{$field} has a non-callable validator");
        }

        $result = $validator($value, $field, $data, $isCreate);
        if ($result === false) {
            throw new ModelValidationException("{$field} failed custom validation");
        }

        if (is_string($result) && $result !== '') {
            throw new ModelValidationException($result);
        }
    }
}
