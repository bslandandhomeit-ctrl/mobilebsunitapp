# Repeatable deposit customers

The mobile form accepts more than two buyers. Existing first/second buyer fields
remain compatible with old requests; buyers 3 onward use `additional_customers`:

```json
[{"name":"Third buyer","gender":1,"phone_number":"012345678"}]
```

Name and gender are required per added buyer; phone is optional and follows the
existing Cambodian 9–10 digit, leading-zero validation. There is no fixed UI
customer count limit. Removed fields are excluded from the submitted list.

## Backend deployment required

These adapter changes have also been applied to the sibling `../bsunitapp` UAT
backend source. They have not been deployed to the running UAT server.
Before enabling saving on UAT or production:

1. The sibling backend already contains the migration. For another checkout, copy it from `backend-adapters/bsunits/migrations/` into the
   backend's `database/migrations/` directory and run `php artisan migrate` on
   its actual deposit request database.
2. Apply the model/controller/route changes from `backend-adapters/bsunits/`.
   Register `GET /api/unit_deposit_requests/customer-capabilities` before the
   `/{id}` route. It returns `data.additional_customers: true` only when the
   storage column exists. Refresh any route cache using the backend deployment
   procedure.
3. If canonical conversion is enabled, apply the converter, amendment, and
   revision adapter changes too. Additional buyers become canonical account
   members, are preserved in contact evidence, and are protected by the existing
   buyer identity review guards. The legacy Beloan profile still represents the
   primary contact; the canonical member table holds the full buyer list.
4. Verify in UAT: submit at least three customers, reload deposit and contract
   details, approve, verify all canonical buyer memberships, and transfer the
   deposit to ensure the additional buyer list is copied.

The app checks the capability endpoint before POSTing a request with additional
buyers. Old servers cannot silently create a two-buyer request from a larger form.
No backend deployment or database migration has been executed by this change.
Historical requests have null/empty additional customers and remain readable.
Contract document templates, signatures, and legacy Beloan screens that enumerate
only two buyers require separate backend review before using this for legal
contract generation; this change adds deposit capture and canonical memberships.

## Local verification

```sh
flutter test test/request_flows_test.dart
/opt/homebrew/opt/php@7.4/bin/php tools/unified-db/test-additional-customers.php
```

The PHP check uses the sibling backend source without a database connection. It
validates a four-buyer payload, rejects invalid added fields, verifies the model
JSON round-trip and the transfer field list. It does not replace a deployed UAT
approval/conversion check.
