<?php

return [
    /*
     * Days an account must be live (first opening order fulfilled) before it
     * counts in the reorder-rate denominator. Section 11: "define maturity
     * consistently before using the number." Change it here, nowhere else.
     */
    'maturity_days' => (int) env('FUELLINE_MATURITY_DAYS', 21),

    // Auto-geocode accounts (city/state → map pin) on save. Off in tests.
    'geocoding' => (bool) env('FUELLINE_GEOCODING', true),

    // Order guardrails from the 90-Day Plan (Section 6).
    'minimum_order_units' => 25,
    'order_increment' => 5,
    'msrp' => 15.00,

    /*
     * 90-Day Guaranteed Buyback agreement. The version hash is derived from the
     * exact terms text shown at signing, so terms can be revised later without
     * invalidating old signatures. Keep counsel in the loop on this text.
     */
    'buyback_terms' => <<<'TERMS'
90-DAY GUARANTEED BUYBACK AGREEMENT

Freedom Fuel ("Company") extends the following guarantee to the wholesale partner identified below ("Partner") for the Partner's pilot order:

1. GUARANTEE. If Freedom Fuel product from the pilot order does not sell within ninety (90) days of the pilot order date, the Company will repurchase unsold, unopened, resalable inventory at the original wholesale cost paid by the Partner.

2. ELIGIBLE INVENTORY. Only product that is unopened, undamaged, within its labeled shelf life, and in resalable condition qualifies for repurchase. Product must have been stored per label directions.

3. PROCESS. To exercise this guarantee, the Partner must notify the Company in writing within one hundred (100) days of the pilot order date. The Company will arrange return shipping and issue a refund or credit within fourteen (14) days of receiving the returned inventory.

4. SCOPE. This guarantee applies to the pilot (first) order only. It does not apply to reorders, which are placed at the Partner's discretion based on demonstrated sell-through.

5. AGREEMENT. By signing electronically below, the signer certifies that they are authorized to bind the Partner, and consents to conduct this transaction by electronic means under the U.S. ESIGN Act.
TERMS,
];
