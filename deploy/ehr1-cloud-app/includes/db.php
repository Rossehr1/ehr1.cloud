<?php
/**
 * PDO connection helper.
 */
declare(strict_types=1);

/**
 * @param array<string,mixed> $config
 * @return PDO
 */
function ehr1_pdo(array $config): PDO
{
    $db = $config['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        (int) $db['port'],
        $db['name'],
        $db['charset']
    );
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

/**
 * True when supplemental_ep_paid.payload_json exists (migration 06 applied).
 */
function ehr1_supplemental_ep_paid_has_payload_json(?PDO $pdo): bool
{
    if (!$pdo instanceof PDO) {
        return false;
    }
    static $yes = null;
    if ($yes !== null) {
        return $yes;
    }
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($db) || $db === '') {
            $yes = false;

            return false;
        }
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $st->execute([$db, 'supplemental_ep_paid', 'payload_json']);
        $yes = ((int) $st->fetchColumn() > 0);
    } catch (Throwable $e) {
        $yes = false;
    }

    return $yes;
}
