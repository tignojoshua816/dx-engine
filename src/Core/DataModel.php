<?php
/**
 * DX-Engine — DataModel (ORM-lite Base)
 * -----------------------------------------------------------------------
 * All application models extend this class.  It wires a "field map"
 * definition to a physical table/view via PDO and provides automatic
 * CRUD, validation, and relationship resolution.
 *
 * Field-map entry shape:
 *   'field_key' => [
 *       'column'    => 'physical_column_name',   // required
 *       'type'      => 'string|int|float|bool|date|datetime|email|phone|text',
 *       'label'     => 'Human-readable label',
 *       'required'  => true|false,
 *       'rules'     => [],   // extra validation rule strings
 *       'default'   => null, // optional default value
 *       'readonly'  => false,// excluded from INSERT/UPDATE
 *       'relation'  => [     // optional foreign-key meta (used by DX pre-processor)
 *           'model'       => \App\Models\SomeModel::class,
 *           'foreign_key' => 'other_table_id',
 *           'type'        => 'belongs_to|has_many',
 *       ],
 *   ]
 */

namespace DXEngine\Core;

use PDO;

abstract class DataModel
{
    /* ------------------------------------------------------------------ */
    /*  Abstract contract                                                   */
    /* ------------------------------------------------------------------ */

    /** Physical table or view name. */
    abstract protected function table(): string;

    /**
     * Field map: logical key => definition array.
     * Subclasses override this to declare their schema.
     *
     * @return array<string, array<string, mixed>>
     */
    abstract protected function fieldMap(): array;

    /* ------------------------------------------------------------------ */
    /*  Connection                                                          */
    /* ------------------------------------------------------------------ */

    private static ?PDO $pdo = null;

    /** Boot the PDO connection once (call from index.php or a bootstrap file). */
    public static function boot(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    protected function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException(
                'DataModel: PDO connection not initialised. Call DataModel::boot($pdo) first.'
            );
        }
        return self::$pdo;
    }

    /* ------------------------------------------------------------------ */
    /*  Primary-key convention                                              */
    /* ------------------------------------------------------------------ */

    /** Override in subclass if PK column differs. */
    protected function primaryKey(): string
    {
        return 'id';
    }

    /* ------------------------------------------------------------------ */
    /*  Schema introspection helpers                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Returns all writable columns (those not marked readonly) mapped as
     *   logical_key => physical_column
     */
    public function writableColumns(): array
    {
        $map = [];
        foreach ($this->fieldMap() as $key => $def) {
            if (empty($def['readonly'])) {
                $map[$key] = $def['column'] ?? $key;
            }
        }
        return $map;
    }

    /**
     * Returns the full field map enriched with runtime defaults.
     */
    public function schema(): array
    {
        $out = [];
        foreach ($this->fieldMap() as $key => $def) {
            $out[$key] = array_merge([
                'column'   => $key,
                'type'     => 'string',
                'label'    => ucwords(str_replace('_', ' ', $key)),
                'required' => false,
                'rules'    => [],
                'default'  => null,
                'readonly' => false,
            ], $def);
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /*  Validation                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Validate a flat key=>value payload against the field map.
     *
     * @return array{ valid: bool, errors: array<string, string> }
     */
    public function validate(array $data): array
    {
        $errors = [];

        foreach ($this->schema() as $key => $def) {
            $value = $data[$key] ?? null;

            // Required
            if ($def['required'] && ($value === null || $value === '')) {
                $errors[$key] = "{$def['label']} is required.";
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            // Type coercion check
            switch ($def['type']) {
                case 'int':
                    if (!filter_var($value, FILTER_VALIDATE_INT)) {
                        $errors[$key] = "{$def['label']} must be a whole number.";
                    }
                    break;
                case 'float':
                    if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                        $errors[$key] = "{$def['label']} must be a number.";
                    }
                    break;
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$key] = "{$def['label']} must be a valid email address.";
                    }
                    break;
                case 'date':
                    $d = \DateTime::createFromFormat('Y-m-d', $value);
                    if (!$d || $d->format('Y-m-d') !== $value) {
                        $errors[$key] = "{$def['label']} must be a valid date (YYYY-MM-DD).";
                    }
                    break;
                case 'phone':
                    if (!preg_match('/^[0-9\+\-\(\)\s]{7,20}$/', $value)) {
                        $errors[$key] = "{$def['label']} must be a valid phone number.";
                    }
                    break;
            }

            // Custom regex rules
            foreach (($def['rules'] ?? []) as $rule) {
                if (str_starts_with($rule, 'regex:')) {
                    $pattern = substr($rule, 6);
                    if (!preg_match($pattern, (string) $value)) {
                        $errors[$key] = "{$def['label']} format is invalid.";
                    }
                }
                if (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (strlen((string) $value) < $min) {
                        $errors[$key] = "{$def['label']} must be at least {$min} characters.";
                    }
                }
                if (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (strlen((string) $value) > $max) {
                        $errors[$key] = "{$def['label']} may not exceed {$max} characters.";
                    }
                }
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /* ------------------------------------------------------------------ */
    /*  CRUD                                                                */
    /* ------------------------------------------------------------------ */

    /** Find one row by PK. Returns array or null. */
    public function find(int|string $id): ?array
    {
        $sql  = "SELECT * FROM {$this->table()} WHERE {$this->primaryKey()} = :id LIMIT 1";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Find rows matching $conditions (key=>value AND). */
    public function where(array $conditions = [], string $orderBy = '', int $limit = 0): array
    {
        $params  = [];
        $clauses = [];

        foreach ($conditions as $column => $value) {
            $safeColumn = $this->sanitizeIdentifier((string) $column);
            $token      = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $column);
            $placeholder = ':w_' . $token;

            $clauses[] = "{$safeColumn} = {$placeholder}";
            $params[$placeholder] = $value;
        }

        $sql = "SELECT * FROM {$this->table()}";
        if ($clauses) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }
        if ($orderBy) {
            $sql .= ' ORDER BY ' . $this->sanitizeOrderBy($orderBy);
        }
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Return all rows (use sparingly on large tables). */
    public function all(string $orderBy = ''): array
    {
        return $this->where([], $orderBy);
    }

    /**
     * Insert a new row.
     *
     * @return int|string  Last insert ID.
     */
    public function insert(array $data): int|string
    {
        $writable = $this->writableColumns();   // logical_key => physical_col
        $cols     = [];
        $placeholders = [];
        $params   = [];

        foreach ($writable as $key => $col) {
            if (array_key_exists($key, $data)) {
                $cols[]                   = $col;
                $placeholders[]           = ":v_{$key}";
                $params[":v_{$key}"]      = $data[$key];
            }
        }

        if (empty($cols)) {
            throw new \InvalidArgumentException('DataModel::insert — no writable fields provided.');
        }

        $sql  = "INSERT INTO {$this->table()} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $this->pdo()->lastInsertId();
    }

    /**
     * Update a row identified by PK.
     *
     * @return int  Rows affected.
     */
    public function update(int|string $id, array $data): int
    {
        $writable = $this->writableColumns();
        $sets     = [];
        $params   = [':pk' => $id];

        foreach ($writable as $key => $col) {
            if (array_key_exists($key, $data)) {
                $sets[]               = "{$col} = :v_{$key}";
                $params[":v_{$key}"]  = $data[$key];
            }
        }

        if (empty($sets)) {
            return 0;
        }

        $sql  = "UPDATE {$this->table()} SET " . implode(', ', $sets) . " WHERE {$this->primaryKey()} = :pk";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Delete a row by PK.
     *
     * @return int  Rows affected.
     */
    public function delete(int|string $id): int
    {
        $sql  = "DELETE FROM {$this->table()} WHERE {$this->primaryKey()} = :id";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount();
    }

    /* ------------------------------------------------------------------ */
    /*  Relationship resolution                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Eager-load a belongs_to relation for a row.
     *
     * Example:
     *   $patientRow = (new PatientModel)->find(5);
     *   $dept       = (new PatientModel)->relatedOne($patientRow, 'department_id');
     */
    public function relatedOne(array $row, string $fieldKey): ?array
    {
        $def = $this->schema()[$fieldKey] ?? null;
        if (!$def || !isset($def['relation'])) {
            return null;
        }
        $rel   = $def['relation'];
        $value = $row[$def['column']] ?? null;
        if ($value === null) {
            return null;
        }
        /** @var DataModel $related */
        $related = new $rel['model']();
        return $related->find($value);
    }

    /**
     * Eager-load a has_many relation.
     *
     * Example:
     *   $admissionRow = (new AdmissionModel)->find(3);
     *   $notes        = (new AdmissionModel)->relatedMany($admissionRow, 'notes');
     */
    public function relatedMany(array $row, string $fieldKey): array
    {
        $def = $this->schema()[$fieldKey] ?? null;
        if (!$def || !isset($def['relation'])) {
            return [];
        }
        $rel     = $def['relation'];
        $pkValue = $row[$this->primaryKey()] ?? null;
        if ($pkValue === null) {
            return [];
        }
        /** @var DataModel $related */
        $related = new $rel['model']();
        return $related->where([$rel['foreign_key'] => $pkValue]);
    }

    /* ------------------------------------------------------------------ */
    /*  Metadata export (consumed by DXController → JSON)                  */
    /* ------------------------------------------------------------------ */

    /**
     * Returns the field map in a format safe to embed in the JSON Metadata
     * Bridge (strips PHP class references, keeps front-end-relevant data).
     */
    public function frontendSchema(): array
    {
        $out = [];
        foreach ($this->schema() as $key => $def) {
            $out[$key] = [
                'key'      => $key,
                'label'    => $def['label'],
                'type'     => $def['type'],
                'required' => $def['required'],
                'rules'    => $def['rules'] ?? [],
                'default'  => $def['default'],
                'readonly' => $def['readonly'],
            ];
        }
        return $out;
    }

    /**
     * Restrict identifiers used in dynamic SQL fragments.
     * Allows: letters, numbers, underscore.
     */
    protected function sanitizeIdentifier(string $identifier): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("Invalid SQL identifier: {$identifier}");
        }
        return $identifier;
    }

    /**
     * Restrict ORDER BY fragment to "column" or "column ASC|DESC".
     */
    protected function sanitizeOrderBy(string $orderBy): string
    {
        $orderBy = trim($orderBy);
        if ($orderBy === '') {
            return $orderBy;
        }

        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)(\s+(ASC|DESC))?$/i', $orderBy, $m)) {
            throw new \InvalidArgumentException("Invalid ORDER BY clause: {$orderBy}");
        }

        $column = $this->sanitizeIdentifier($m[1]);
        $dir    = isset($m[3]) ? strtoupper($m[3]) : '';
        return $dir ? "{$column} {$dir}" : $column;
    }
}
