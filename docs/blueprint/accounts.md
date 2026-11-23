# Accounts

How a visitor registers, and how that request writes a row in `users`. This is the reference path for `US-ACC-01`, `US-ACC-02`, and `US-ACC-03`.

The shared shape of a screen is in [blueprint.md](../blueprint.md).

## Showing the page

```text
GET /register
  guest middleware
  Laravel\Fortify\Http\Controllers\RegisteredUserController::create
    RegisterViewResponse
      FortifyServiceProvider::configureViews()
        Inertia::render('auth/register', { passwordRules })
          resources/js/pages/auth/register.tsx
            AuthLayout
```

Fortify registers the route because `Features::registration()` is enabled in `config/fortify.php`. The app does not declare `/register` in `routes/web.php`. `FortifyServiceProvider` tells Fortify to render an Inertia page instead of a Blade view:

```70:72:app/Providers/FortifyServiceProvider.php
        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));
```

`passwordRules` is the only prop this page owns. `auth.user` is null here, because the guest middleware rejects anyone who is already signed in.

The page posts with the Wayfinder helper for `POST /register` (`register.store`):

```20:22:resources/js/pages/auth/register.tsx
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
```

`store` is imported from `@/routes/register`. `store.form()` supplies the action and method. The component does not contain the URL string.

## Saving the user

```text
POST /register
  guest middleware
  RegisteredUserController::store
    lowercase the email (fortify.lowercase_usernames)
    CreateNewUser::create($input)
      validate name, email, password
      User::create(...)          → INSERT INTO users
    Registered event
    guard login + session regenerate
    redirect to /dashboard
      verified middleware
        email not verified → redirect to the verification notice
```

`CreateNewUser` is the app’s hook. Fortify calls it because `FortifyServiceProvider` registers it with `Fortify::createUsersUsing(CreateNewUser::class)`.

```20:32:app/Actions/Fortify/CreateNewUser.php
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
```

Validation rules:

| Field | Rules | Defined in |
| --- | --- | --- |
| `name` | required, string, max 255 | `App\Concerns\ProfileValidationRules` |
| `email` | required, string, email, max 255, unique on `User` | `App\Concerns\ProfileValidationRules` |
| `password` | required, string, `Password::default()`, confirmed | `App\Concerns\PasswordValidationRules` |

`password_confirmation` is required by the `confirmed` rule. It is not a column.

If validation fails, `validate()` throws. Laravel redirects back to `/register` with the error bag. The `<Form>` render prop receives `errors.name`, `errors.email`, `errors.password`, and `errors.password_confirmation`. No row is written.

If validation passes, `User::create` inserts a row. The model hashes `password` through a cast, so the action passes the plain password and the `users.password` column stores a hash.

```42:48:app/Models/User.php
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
```

Only `name`, `email`, and `password` are fillable. Other columns cannot be mass-assigned from the request.

After the insert, Fortify fires `Illuminate\Auth\Events\Registered`, logs the user in, and regenerates the session. The response redirects to `config('fortify.home')`, which is `/dashboard`.

`User` implements `MustVerifyEmail`, and `email_verified_at` is still null. The dashboard route uses the `auth` and `verified` middleware (`routes/web.php`), so the verified middleware redirects the new user to the email verification notice (`verification.notice`) before they can open the dashboard.

## The `users` table

Created by `database/migrations/0001_01_01_000000_create_users_table.php`, then extended by `database/migrations/2025_08_14_170933_add_two_factor_columns_to_users_table.php`.

| Column | Set at registration |
| --- | --- |
| `id` | database |
| `name` | form |
| `email` | form, lowercased, unique |
| `email_verified_at` | null until the address is verified |
| `password` | form, stored hashed |
| `two_factor_secret` | null |
| `two_factor_recovery_codes` | null |
| `two_factor_confirmed_at` | null |
| `remember_token` | null |
| `created_at`, `updated_at` | database |

`password_reset_tokens` and `sessions` are created in the same migration. Registration writes the session through the login call. It does not write a password reset token.

Registration is the exception that uses Fortify’s controller. Application screens use controllers under `app/Http/Controllers`.
