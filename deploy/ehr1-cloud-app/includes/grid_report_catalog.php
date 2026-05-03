<?php
/**
 * Column definitions: sql uses p (core_npi_provider) and ep (supplemental_ep_paid).
 * Use COALESCE(p.npi, ep.npi) for NPI and satellite subqueries when EP-only rows are included.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ep_paid_helpers.php';

/**
 * @return array<string, array{label:string, sql:string}>
 */
function ehr1_grid_report_field_catalog(?PDO $pdo = null): array
{
    $base = [
        'npi' => ['label' => 'NPI', 'sql' => 'COALESCE(p.npi, ep.npi)'],
        'entity_type_code' => ['label' => 'Entity type', 'sql' => 'p.entity_type_code'],
        'replacement_npi' => ['label' => 'Replacement NPI', 'sql' => 'p.replacement_npi'],
        'employer_identification_number' => ['label' => 'EIN', 'sql' => 'p.employer_identification_number'],
        'provider_organization_name' => ['label' => 'Organization name', 'sql' => 'p.provider_organization_name'],
        'provider_last_name' => ['label' => 'Last name', 'sql' => 'p.provider_last_name'],
        'provider_first_name' => ['label' => 'First name', 'sql' => 'p.provider_first_name'],
        'provider_middle_name' => ['label' => 'Middle name', 'sql' => 'p.provider_middle_name'],
        'provider_name_prefix' => ['label' => 'Name prefix', 'sql' => 'p.provider_name_prefix'],
        'provider_name_suffix' => ['label' => 'Name suffix', 'sql' => 'p.provider_name_suffix'],
        'provider_credential' => ['label' => 'Credential', 'sql' => 'p.provider_credential'],
        'mailing_address_line1' => ['label' => 'Mailing line 1', 'sql' => 'p.mailing_address_line1'],
        'mailing_address_line2' => ['label' => 'Mailing line 2', 'sql' => 'p.mailing_address_line2'],
        'mailing_city' => ['label' => 'Mailing city', 'sql' => 'p.mailing_city'],
        'mailing_state' => ['label' => 'Mailing state', 'sql' => 'p.mailing_state'],
        'mailing_postal_code' => ['label' => 'Mailing ZIP', 'sql' => 'p.mailing_postal_code'],
        'mailing_country' => ['label' => 'Mailing country', 'sql' => 'p.mailing_country'],
        'mailing_phone' => ['label' => 'Mailing phone', 'sql' => 'p.mailing_phone'],
        'mailing_fax' => ['label' => 'Mailing fax', 'sql' => 'p.mailing_fax'],
        'practice_address_line1' => ['label' => 'Practice line 1', 'sql' => 'p.practice_address_line1'],
        'practice_address_line2' => ['label' => 'Practice line 2', 'sql' => 'p.practice_address_line2'],
        'practice_city' => ['label' => 'Practice city', 'sql' => 'p.practice_city'],
        'practice_state' => ['label' => 'Practice state', 'sql' => 'p.practice_state'],
        'practice_postal_code' => ['label' => 'Practice ZIP', 'sql' => 'p.practice_postal_code'],
        'practice_country' => ['label' => 'Practice country', 'sql' => 'p.practice_country'],
        'practice_phone' => ['label' => 'Practice phone', 'sql' => 'p.practice_phone'],
        'practice_fax' => ['label' => 'Practice fax', 'sql' => 'p.practice_fax'],
        'enumeration_date' => ['label' => 'Enumeration date', 'sql' => 'p.enumeration_date'],
        'last_update_date' => ['label' => 'Last update', 'sql' => 'p.last_update_date'],
        'npi_deactivation_reason_code' => ['label' => 'Deactivation reason', 'sql' => 'p.npi_deactivation_reason_code'],
        'npi_deactivation_date' => ['label' => 'Deactivation date', 'sql' => 'p.npi_deactivation_date'],
        'npi_reactivation_date' => ['label' => 'Reactivation date', 'sql' => 'p.npi_reactivation_date'],
        'provider_sex_code' => ['label' => 'Sex code', 'sql' => 'p.provider_sex_code'],
        'authorized_official_last_name' => ['label' => 'Auth. official last', 'sql' => 'p.authorized_official_last_name'],
        'authorized_official_first_name' => ['label' => 'Auth. official first', 'sql' => 'p.authorized_official_first_name'],
        'authorized_official_middle_name' => ['label' => 'Auth. official middle', 'sql' => 'p.authorized_official_middle_name'],
        'authorized_official_title' => ['label' => 'Auth. official title', 'sql' => 'p.authorized_official_title'],
        'authorized_official_phone' => ['label' => 'Auth. official phone', 'sql' => 'p.authorized_official_phone'],
        'healthcare_provider_taxonomy_code_1' => ['label' => 'Taxonomy 1', 'sql' => 'p.healthcare_provider_taxonomy_code_1'],
        'provider_license_number_1' => ['label' => 'License 1', 'sql' => 'p.provider_license_number_1'],
        'provider_license_number_state_1' => ['label' => 'License state 1', 'sql' => 'p.provider_license_number_state_1'],
        'healthcare_provider_primary_taxonomy_switch_1' => ['label' => 'Primary tax switch 1', 'sql' => 'p.healthcare_provider_primary_taxonomy_switch_1'],
        'healthcare_provider_taxonomy_code_2' => ['label' => 'Taxonomy 2', 'sql' => 'p.healthcare_provider_taxonomy_code_2'],
        'provider_license_number_2' => ['label' => 'License 2', 'sql' => 'p.provider_license_number_2'],
        'provider_license_number_state_2' => ['label' => 'License state 2', 'sql' => 'p.provider_license_number_state_2'],
        'healthcare_provider_primary_taxonomy_switch_2' => ['label' => 'Primary tax switch 2', 'sql' => 'p.healthcare_provider_primary_taxonomy_switch_2'],
        'source_batch_id' => ['label' => 'Source batch ID', 'sql' => 'COALESCE(p.source_batch_id, ep.source_batch_id)'],
        'agg_endpoints' => [
            'label' => 'Endpoints (concat)',
            'sql' => '(SELECT GROUP_CONCAT(CONCAT_WS(":", COALESCE(e.endpoint_type,""), COALESCE(e.endpoint_url,"")) SEPARATOR " | ") FROM core_npi_endpoint e WHERE e.npi = COALESCE(p.npi, ep.npi))',
        ],
        'agg_other_names' => [
            'label' => 'Other org names (concat)',
            'sql' => '(SELECT GROUP_CONCAT(COALESCE(o.provider_other_organization_name,"") SEPARATOR " | ") FROM core_npi_other_name o WHERE o.npi = COALESCE(p.npi, ep.npi))',
        ],
        'agg_practice_locations' => [
            'label' => 'Other practice locs (concat)',
            'sql' => '(SELECT GROUP_CONCAT(CONCAT_WS(", ", COALESCE(pl.pl_address_line1,""), COALESCE(pl.pl_city,""), COALESCE(pl.pl_state,"")) SEPARATOR " | ") FROM core_npi_practice_location pl WHERE pl.npi = COALESCE(p.npi, ep.npi))',
        ],
        'agg_rel_parent_npis' => [
            'label' => 'Linked parent NPIs (practice/group)',
            'sql' => '(SELECT GROUP_CONCAT(DISTINCT r.parent_npi ORDER BY r.parent_npi SEPARATOR " | ") FROM core_npi_relationship r WHERE r.child_npi = COALESCE(p.npi, ep.npi))',
        ],
        'agg_rel_child_npis' => [
            'label' => 'Linked child NPIs (individuals)',
            'sql' => '(SELECT GROUP_CONCAT(DISTINCT r.child_npi ORDER BY r.child_npi SEPARATOR " | ") FROM core_npi_relationship r WHERE r.parent_npi = COALESCE(p.npi, ep.npi))',
        ],
    ];

    // EP PAID: per-field columns from ep_paid_headers.generated.json (joined by NPI to master providers).
    return array_merge($base, ehr1_ep_paid_grid_columns_from_manifest($pdo));
}

/**
 * Default columns when none selected (spreadsheet-friendly).
 *
 * @return list<string>
 */
function ehr1_grid_report_default_columns(): array
{
    return [
        'npi', 'entity_type_code', 'provider_organization_name', 'provider_last_name', 'provider_first_name',
        'practice_city', 'practice_state', 'practice_postal_code', 'practice_phone',
        'healthcare_provider_taxonomy_code_1',
    ];
}
