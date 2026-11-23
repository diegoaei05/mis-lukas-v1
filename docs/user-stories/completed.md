# Completed user stories

Behavior that is already in the app. These stories are the regression bar. The index with summaries is [user-stories.md](../user-stories.md).

## Module: Accounts

Registration, sign-in, and the signed-in user. This module came with the Laravel starter kit.

<a id="us-acc-01"></a>

### US-ACC-01 — Open the registration page

**Status:** Done

**Story:** As a visitor, I want a registration page, so that I can create an account.

**Acceptance:**

- `GET /register` is available only to guests.
- The page is the Inertia component `auth/register` (`resources/js/pages/auth/register.tsx`), inside the auth layout.
- The form asks for name, email, password, and password confirmation.
- The page receives `passwordRules` so the password fields can show the current password policy.

<a id="us-acc-02"></a>

### US-ACC-02 — Create an account

**Status:** Done

**Story:** As a visitor, I want to submit my name, email, and password, so that I have an account I can sign in with.

**Acceptance:**

- Submitting the form sends `POST /register`.
- Name is required, a string, and at most 255 characters.
- Email is required, a valid email, at most 255 characters, and unique on `users.email`. The address is stored in lowercase.
- Password is required, confirmed, and follows `Password::defaults()` (stricter rules in production).
- A row is inserted in `users` with `name`, `email`, and a hashed `password`. `email_verified_at` stays empty until the address is verified.
- The new user is signed in on the `web` guard and the session id is regenerated.
- The browser is sent to `/dashboard`. Because the email is not verified yet, the verified middleware sends them to the email verification notice instead of the dashboard.

<a id="us-acc-03"></a>

### US-ACC-03 — See registration errors on the form

**Status:** Done

**Story:** As a visitor, I want invalid registration input to stay on the form with field errors, so that I can correct it without losing the rest of what I typed.

**Acceptance:**

- Failed validation redirects back to the registration page with Inertia errors for the invalid fields.
- Password and password confirmation are cleared after a successful submit. They are not left filled when validation fails and the user retries.
- The submit control is disabled while the request is in progress.
