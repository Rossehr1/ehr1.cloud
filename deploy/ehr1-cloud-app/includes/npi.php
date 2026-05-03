<?php
/**
 * NPI normalization and entity-type labels (NPPES-style).
 */
declare(strict_types=1);

/**
 * Return 10-digit NPI string or null if invalid.
 */
function ehr1_normalize_npi(?string $raw): ?string
{
    if ($raw === null || $raw === '') {
        return null;
    }
    $d = preg_replace('/\D/', '', $raw);
    if ($d === null || strlen($d) !== 10) {
        return null;
    }
    return $d;
}

/**
 * @return array{label:string,code:?int}
 */
function ehr1_entity_type(?int $code): array
{
    return match ($code) {
        1 => ['label' => 'Individual', 'code' => 1],
        2 => ['label' => 'Organization', 'code' => 2],
        default => ['label' => 'Unknown', 'code' => $code],
    };
}
