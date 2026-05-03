<?php
/**
 * Report queries: NPI-first, cross-reference when no exact NPI, practice hierarchy.
 */
declare(strict_types=1);

/**
 * @return array<string,mixed>|null
 */
function ehr1_fetch_provider(PDO $pdo, string $npi): ?array
{
    $st = $pdo->prepare(
        'SELECT * FROM core_npi_provider WHERE npi = :npi LIMIT 1'
    );
    $st->execute(['npi' => $npi]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/**
 * @return list<array<string,mixed>>
 */
function ehr1_children_for_parent(PDO $pdo, string $parentNpi): array
{
    $st = $pdo->prepare(
        'SELECT r.relationship_id, r.parent_npi, r.child_npi, r.relationship_type, r.notes,
                p.entity_type_code, p.provider_last_name, p.provider_first_name,
                p.provider_organization_name, p.practice_city, p.practice_state
         FROM core_npi_relationship r
         INNER JOIN core_npi_provider p ON p.npi = r.child_npi
         WHERE r.parent_npi = :p
         ORDER BY p.provider_last_name, p.provider_first_name'
    );
    $st->execute(['p' => $parentNpi]);
    return $st->fetchAll();
}

/**
 * @return list<array<string,mixed>>
 */
function ehr1_parents_for_individual(PDO $pdo, string $childNpi): array
{
    $st = $pdo->prepare(
        'SELECT r.parent_npi, r.relationship_type, r.notes,
                p.provider_organization_name, p.practice_city, p.practice_state, p.entity_type_code
         FROM core_npi_relationship r
         INNER JOIN core_npi_provider p ON p.npi = r.parent_npi
         WHERE r.child_npi = :c'
    );
    $st->execute(['c' => $childNpi]);
    return $st->fetchAll();
}

/**
 * Build human-readable match reasons for a provider row vs search criteria.
 *
 * @param array<string,mixed> $row
 * @return list<string>
 */
function ehr1_match_reasons(array $row, ?string $ln, ?string $fn, ?string $org, ?string $city, ?string $state, ?string $zip5): array
{
    $reasons = [];
    $ln = $ln !== null && $ln !== '' ? $ln : null;
    $fn = $fn !== null && $fn !== '' ? $fn : null;
    $org = $org !== null && $org !== '' ? $org : null;
    $city = $city !== null && $city !== '' ? $city : null;
    $state = $state !== null && $state !== '' ? strtoupper($state) : null;
    $zip5 = $zip5 !== null && $zip5 !== '' ? preg_replace('/\D/', '', $zip5) : null;
    if ($zip5 !== null && strlen($zip5) > 5) {
        $zip5 = substr($zip5, 0, 5);
    }

    if ($ln !== null && stripos((string) ($row['provider_last_name'] ?? ''), $ln) !== false) {
        $reasons[] = 'last name';
    }
    if ($fn !== null && stripos((string) ($row['provider_first_name'] ?? ''), $fn) !== false) {
        $reasons[] = 'first name';
    }
    if ($org !== null) {
        $on = (string) ($row['provider_organization_name'] ?? '');
        if (stripos($on, $org) !== false) {
            $reasons[] = 'organization name';
        }
    }
    if ($city !== null && stripos((string) ($row['practice_city'] ?? ''), $city) !== false) {
        $reasons[] = 'practice city';
    }
    if ($state !== null && strtoupper((string) ($row['practice_state'] ?? '')) === $state) {
        $reasons[] = 'state';
    }
    if ($zip5 !== null) {
        $pz = preg_replace('/\D/', '', (string) ($row['practice_postal_code'] ?? ''));
        if ($pz !== '' && strncmp($pz, $zip5, min(5, strlen($zip5))) === 0) {
            $reasons[] = 'ZIP';
        }
    }

    return $reasons;
}

/**
 * Cross-reference: OR across provided fields; filter in PHP by at least one reason.
 *
 * @return list<array<string,mixed>>
 */
function ehr1_crossref_search(
    PDO $pdo,
    ?string $lastName,
    ?string $firstName,
    ?string $orgName,
    ?string $city,
    ?string $state,
    ?string $zip,
    int $limit = 75
): array {
    $lastName = $lastName !== null && trim($lastName) !== '' ? trim($lastName) : null;
    $firstName = $firstName !== null && trim($firstName) !== '' ? trim($firstName) : null;
    $orgName = $orgName !== null && trim($orgName) !== '' ? trim($orgName) : null;
    $city = $city !== null && trim($city) !== '' ? trim($city) : null;
    $state = $state !== null && trim($state) !== '' ? strtoupper(trim($state)) : null;
    $zip = $zip !== null && trim($zip) !== '' ? preg_replace('/\D/', '', trim($zip)) : null;
    if ($zip !== null && strlen($zip) > 5) {
        $zip = substr($zip, 0, 5);
    }

    $has = $lastName || $firstName || $orgName || $city || $state || $zip;
    if (!$has) {
        return [];
    }

    $parts = [];
    $params = [];
    if ($lastName !== null) {
        $parts[] = 'p.provider_last_name LIKE :ln';
        $params['ln'] = '%' . $lastName . '%';
    }
    if ($firstName !== null) {
        $parts[] = 'p.provider_first_name LIKE :fn';
        $params['fn'] = '%' . $firstName . '%';
    }
    if ($orgName !== null) {
        $parts[] = '(p.provider_organization_name LIKE :org OR EXISTS (
            SELECT 1 FROM core_npi_other_name o WHERE o.npi = p.npi AND o.provider_other_organization_name LIKE :org2
        ))';
        $params['org'] = '%' . $orgName . '%';
        $params['org2'] = '%' . $orgName . '%';
    }
    if ($city !== null) {
        $parts[] = 'p.practice_city LIKE :city';
        $params['city'] = '%' . $city . '%';
    }
    if ($state !== null) {
        $parts[] = 'p.practice_state = :st';
        $params['st'] = $state;
    }
    if ($zip !== null) {
        $parts[] = 'REPLACE(REPLACE(p.practice_postal_code, "-", ""), " ", "") LIKE :zip';
        $params['zip'] = $zip . '%';
    }

    $whereOr = implode(' OR ', $parts);
    $sql = "SELECT p.* FROM core_npi_provider p WHERE ($whereOr)
            ORDER BY p.provider_last_name, p.provider_first_name, p.npi
            LIMIT " . (int) $limit;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    $out = [];
    foreach ($rows as $row) {
        $reasons = ehr1_match_reasons($row, $lastName, $firstName, $orgName, $city, $state, $zip);
        if ($reasons !== []) {
            $row['match_reason'] = implode(', ', $reasons);
            $row['match_count'] = count($reasons);
            $out[] = $row;
        }
    }

    usort($out, static function ($a, $b) {
        return (int) ($b['match_count'] ?? 0) <=> (int) ($a['match_count'] ?? 0);
    });

    return $out;
}
