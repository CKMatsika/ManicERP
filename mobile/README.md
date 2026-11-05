# ManicERP citizen mobile application

Flutter application targeting Android and iOS. It talks to the same versioned
Laravel API as the citizen web portal. Source is complete enough to build and
run, but **this environment has no Flutter/Dart SDK, so no Android or iOS build
has been produced or verified here**.

## What is implemented in source

- Email/password sign-in against `POST /api/v1/auth/login` with a council code.
- Revocable bearer token stored with `flutter_secure_storage`.
- Linked account list, account detail, bills, verified payments and receipts.
- Payment intent creation through `POST /api/v1/payments/intents`. The app shows
  the intent as pending and never issues its own receipt.
- Account linking with a one-time code (`POST /api/v1/account-links`,
  `POST /api/v1/account-links/{id}/verify`).
- Service requests with an offline draft explicitly marked unsent and a retry
  action.
- Error and offline handling, refresh, pagination-ready list screens.

## Build (requires a Flutter SDK)

```sh
cd mobile
flutter pub get
flutter test
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000   # Android emulator
flutter build apk --dart-define=API_BASE_URL=https://erp.example.org
flutter build ios --dart-define=API_BASE_URL=https://erp.example.org         # macOS + Xcode only
```

App icons, splash screens and store metadata are not produced. Store publication
is a later explicit operation.

## Security notes

- No provider or gateway secret is present in the app.
- The token is scoped to one council and one membership type; revocation is
  performed server-side (`POST /api/v1/auth/logout`).
- Payment confirmation is server-verified; browser/app return alone does not
  create a receipt.
- Push notifications and deep links are documented as not yet implemented.
