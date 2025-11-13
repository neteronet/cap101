# TODO: Implement QR Code Scanning for Subsidy Claim Verification with Claim Counter

## Overview
Modify the existing QR code scanning system to increment a claim counter in the database when a subsidy is marked as claimed, allowing tracking of how many times a subsidy has been claimed.

## Tasks
- [x] Modify `pages/api/update_subsidy_claim.php` to increment the `claimed` column instead of setting it to 1, and remove the restriction that prevents multiple claims.
- [x] Update `pages/api/get_subsidy_details.php` to return the `claimed` value as an integer count.
- [x] Adjust the JavaScript logic in `pages/municipal-qrcode_management.php` to display the claim count and allow marking as claimed even if previously claimed (to increment the counter).
- [x] Test the QR scanning, fetching details, and incrementing the claim counter.
- [x] Ensure the database column `claimed` in `assistance_applications` table is of type INT to support counting beyond 1.

## Notes
- The `claimed` column should be changed from a boolean-like field to an integer counter.
- The UI should show the current number of claims (e.g., "Claimed 2 times").
- Allow multiple claims for the same subsidy application.
