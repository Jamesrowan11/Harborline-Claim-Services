<?php

/*
|--------------------------------------------------------------------------
| Branding configuration
|--------------------------------------------------------------------------
| Every brand-facing string in the application resolves through this file
| (and may be overridden at runtime by administrator-managed Settings).
| "Harborline Claim Services" is a PROVISIONAL working name until the owner
| and attorney confirm entity-name availability, trademark clearance,
| domain/social availability, and licensing-language restrictions.
| Renaming the product requires only editing env values / admin settings —
| never a rebuild.
*/

return [
    'name' => env('BRAND_NAME', 'Harborline Claim Services'),
    'legal_name' => env('BRAND_LEGAL_NAME', 'Harborline Claim Services LLC'),
    'parent_company' => env('BRAND_PARENT_COMPANY', 'Northvale Unified Inc.'),
    'tagline' => env('BRAND_TAGLINE', 'Helping You Navigate the Path to Possible Surplus Funds'),
    'case_prefix' => env('BRAND_CASE_PREFIX', 'HCS'),
    'phone' => env('BRAND_PHONE', ''),
    'email' => env('BRAND_EMAIL', ''),
    'address' => env('BRAND_ADDRESS', ''),
    'primary_state' => env('BRAND_PRIMARY_STATE', 'MD'),

    'colors' => [
        'navy' => env('BRAND_COLOR_NAVY', '#1b2a4a'),
        'warm_white' => '#faf7f2',
        'gold' => env('BRAND_COLOR_GOLD', '#b3924f'),
        'soft_gray' => '#8a8f98',
        'burgundy' => env('BRAND_COLOR_BURGUNDY', '#6e2b3a'),
    ],

    // Default case-number format. Admin-editable via Settings ('case_number_format').
    // Tokens: {PREFIX} {YEAR} {STATE} {COUNTY} {SEQ:n}
    'case_number_format' => '{PREFIX}-{YEAR}-{STATE}-{COUNTY}-{SEQ:6}',

    // Mandatory independence disclaimer shown near primary calls to action.
    'disclaimer' => env(
        'BRAND_DISCLAIMER',
        'Harborline Claim Services is not a government agency, court, trustee, county office, or law firm. '
        .'The existence, amount, ownership, and availability of possible funds must be independently verified. '
        .'Recovery is not guaranteed. Legal matters are referred to appropriately licensed attorneys.'
    ),

    'confidentiality_footer' => 'Confidential — prepared by {name}. Contains non-public case information. Do not redistribute.',
];
