<?php

return [
    /*
     * Version stamp recorded against each parental-consent record so a later
     * policy change can be told apart from consent captured under earlier terms.
     */
    'policy_version' => env('COMPLIANCE_POLICY_VERSION', '2026-01'),

    /*
     * Age (years) below which a learner is treated as a minor requiring COPPA /
     * NDPA verifiable parental consent.
     */
    'minor_age' => (int) env('COMPLIANCE_MINOR_AGE', 13),
];
