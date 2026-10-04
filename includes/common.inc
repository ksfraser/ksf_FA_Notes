<?php
/**
 * Shared helpers for the ksf_FA_Notes table gateways.
 *
 * @BABOK Related: FR-NT-001-001
 * @since 1.0.0
 */

/**
 * Escape a value for interpolation into SQL.
 *
 * Inside FA this must go through the live mysqli handle, per the module hard
 * rule (AGENTS_ARCH 10): db_escape() HTML-decodes and is not a general SQL
 * escaper, so it cannot be used for parameter safety. FA has no prepared
 * statements, so every gateway interpolates escaped literals.
 *
 * When there is no mysqli handle — unit tests, CLI, composer-side use — fall
 * back to addslashes() so the pure SQL builders stay testable outside FA.
 * That fallback is never reached in the FA runtime path.
 *
 * @param mixed $value Scalar to escape; arrays and objects are rejected.
 * @return string Escaped literal, quotes included.
 * @since 1.0.0
 */
function notes_db_escape($value)
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    $db = isset($GLOBALS['db']) ? $GLOBALS['db'] : null;

    if ($db instanceof mysqli) {
        return "'" . mysqli_real_escape_string($db, (string) $value) . "'";
    }

    return "'" . addslashes((string) $value) . "'";
}

/**
 * Current timestamp as a MySQL DATETIME literal.
 *
 * @return string 'Y-m-d H:i:s'
 * @since 1.0.0
 */
function notes_now()
{
    return date('Y-m-d H:i:s');
}

/**
 * Build the SET clause of an UPDATE from a whitelisted field map.
 *
 * @param array $data Field => value, already filtered to known columns
 * @param array $columns Allowed column names
 * @return string 'SET a = ..., b = ...' or '' when there is nothing to set
 * @since 1.0.0
 */
function notes_build_set(array $data, array $columns)
{
    $parts = array();

    foreach ($columns as $column) {
        if (array_key_exists($column, $data)) {
            $parts[] = '`' . $column . '` = ' . notes_db_escape($data[$column]);
        }
    }

    return $parts ? 'SET ' . implode(', ', $parts) : '';
}

/**
 * The note categories a note can be filed under.
 *
 * Deliberately a small fixed vocabulary rather than a free-text field, so
 * SEARCH_NOTES can filter on note_type and consumers can offer the same
 * choices. Extend through the filter hook rather than by editing this list.
 *
 * @return array<string, string> Value => label
 * @since 1.0.0
 */
function notes_note_types()
{
    $types = array(
        'Comment' => _('Comment'),
        'Call' => _('Call'),
        'Email' => _('Email'),
        'Meeting' => _('Meeting'),
        'Task' => _('Task'),
        'Document' => _('Document'),
        'Issue' => _('Issue'),
        'Reminder' => _('Reminder'),
    );

    if (function_exists('hook_invoke_all')) {
        $payload = array('types' => $types);
        hook_invoke_all('NOTES_NOTE_TYPES', $payload);
        if (isset($payload['types']) && is_array($payload['types'])) {
            $types = $payload['types'];
        }
    }

    return $types;
}

/**
 * Read a value from a responder's $opts, falling back to $data.
 *
 * FA passes responder arguments as hook_invoke($ext, $method, &$data, $opts):
 * $opts is the caller's argument array and $data is the payload. Accepting
 * both means a responder can be driven either way.
 *
 * @param array|null $opts Caller arguments, may be null
 * @param array      $data Payload, used when $opts has no such key
 * @param string     $key  Key to read
 * @return mixed|null Null when absent in both
 * @since 1.0.0
 */
function notes_opt($opts, array $data, $key)
{
    if (is_array($opts) && array_key_exists($key, $opts) && $opts[$key] !== '') {
        return $opts[$key];
    }

    if (array_key_exists($key, $data) && $data[$key] !== '') {
        return $data[$key];
    }

    return null;
}

