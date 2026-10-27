# Customer registration and Firebase phone verification

The mobile login screen links to Create Account. Customers enter name, birthdate,
gender, Cambodian phone number, optional email/photo, and a confirmed password.
Firebase verifies the phone by SMS; the API verifies the signed ID token before
creating a `customer` role account. Existing phone numbers cannot be overwritten.
No sale-team role or staff permission is accepted from this public form.

After registration the app logs in using Passport and loads `/whoami` roles.
Customers see Home → Projects → Units → Unit details, with logout in Home.
Staff tabs, hold/deposit buttons, and staff comments are hidden. On restart the
shell waits for the server's identity before selecting a customer or staff view.

## Backend installation

These are backend adapter sources, **not an automatic UAT/production deployment**.
Apply the following changes to the BS Units Laravel backend (not Beloan):

| Source in `backend-adapters/bsunits` | Laravel destination |
| --- | --- |
| `Controllers/CustomerRegistrationController.php` | `app/Http/Controllers/Api/CustomerRegistrationController.php` |
| `Services/FirebasePhoneIdentity.php` | `app/Services/FirebasePhoneIdentity.php` |
| `customer_registration.php` | `config/customer_registration.php` |
| `Middleware/EnsureApiAccountEnabled.php` | `app/Http/Middleware/EnsureApiAccountEnabled.php` |
| `Middleware/ThrottleCustomerRegistration.php` | `app/Http/Middleware/ThrottleCustomerRegistration.php` |

Merge the `/customers/register` route from `routes/api.php`, the `customer` entry
in `User::findForPassport`, and `apiAccountEnabled` on the unified catalog routes.
Ensure the existing API route group uses `auth:api` and `apiAccountEnabled`, with
that middleware alias registered in `app/Http/Kernel.php`. The middleware denies
customer requests outside an explicit read allow-list and logout. It removes
staff embeds and action/creator data from catalog responses.

Set in the backend environment:

```dotenv
CUSTOMER_REGISTRATION_ENABLED=true
CUSTOMER_FIREBASE_PROJECT_ID=contract-register
```

Refresh Laravel's config and route caches using the deployment's normal process.
The users table must have a unique phone-number constraint (as in the original
schema). Registration writes the normal BS Units User model and role tables in
a transaction. If those tables have been replaced by read-only unified views,
connect registration to the canonical identity writer before enabling it.

The backend requires OpenSSL and outbound HTTPS to Google's public signing
certificate endpoint. It checks RS256, key ID, signature, project audience,
issuer, subject, expiry, issue time, phone provider, Cambodian number, and a phone
sign-in within the last ten minutes. No Firebase service-account secret is needed.

## Firebase setup and verification

1. Enable Firebase Authentication → Phone for the project and permit Cambodia
   in the SMS region policy. Configure SMS billing/quota as required by Firebase.
2. Verify the app registrations against `lib/firebase_options.dart`. The checked-in
   iOS configuration has no accompanying GoogleService-Info.plist; confirm its
   app ID/API key in Firebase before testing SMS. If replacing the iOS app ID,
   update its encoded `app-…` callback scheme in `ios/Runner/Info.plist` too.
3. Android: register the debug/release SHA fingerprints for both UAT and prod apps.
4. iOS: configure APNs for silent notification verification. The encoded app-ID
   scheme is included for the reCAPTCHA fallback, used by the simulator.
5. UAT initializes Firebase only when registration requests OTP. Crashlytics and
   OneSignal remain production-only. Firebase test phone numbers are configured
   in the Console; the app does not disable verification or hardcode test OTPs.
6. Test invalid/expired OTPs, resend, duplicate phone/email, incorrect token phone,
   a fresh customer login and restart, and staff login. With a customer Passport
   token, verify staff APIs return 403 while catalog reads and logout succeed.

Automated local checks (no SMS sent):

```bash
flutter test test/features/auth/customer_registration_test.dart test/features/auth/user_test.dart
php tools/unified-db/test-customer-registration-security.php
flutter analyze
```

References: [Flutter phone authentication](https://firebase.google.com/docs/auth/flutter/phone-auth),
[server token verification](https://firebase.google.com/docs/auth/admin/verify-id-tokens),
[iOS phone authentication setup](https://firebase.google.com/docs/auth/ios/phone-auth).
