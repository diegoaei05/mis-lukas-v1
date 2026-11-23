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

## Module: Categories

A signed-in user files spending under categories they create, and under subcategories that belong to those categories.

<a id="us-cat-01"></a>

### US-CAT-01 — Create a category and its subcategories

**Status:** Done

**Story:** As a signed-in user, I want a Categories area where I can create a category and then create subcategories that belong to that category, so that later expenses can be filed the way a yogurt purchase is filed under Food and Super/Market.

**Acceptance:**

- A signed-in, verified user can open Categories from the main navigation.
- They can create a category by name, and it appears in their list.
- They can create a subcategory by name on one of their categories, and it appears under that category only.
- A subcategory cannot be created without a category, and it cannot be attached to another user's category.
- A guest who opens Categories is redirected to login.
- An empty name, a duplicate category name for that user, or a duplicate subcategory name inside the same category is shown on the form and is not saved.
- They can delete one of their subcategories. The X removes that subcategory only.

## Module: Expense Types

The acronym a later expense will show as Tipo, and the description of what that acronym means.

<a id="us-typ-01"></a>

### US-TYP-01 — Create an expense type

**Status:** Done

**Story:** As a signed-in user, I want an Expense Types area where I can create a type with an acronym and a description, so that later an expense can use that acronym as its Tipo.

**Acceptance:**

- A signed-in, verified user can open Expense Types from the main navigation.
- They can create a type with an acronym and a description, and it appears in their list.
- A guest who opens Expense Types is redirected to login.
- An empty acronym, an empty description, or an acronym that user already has is shown on the form and is not saved.

