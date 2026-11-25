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

**Story:** As a signed-in user, I want a Categories area where I can create a category, update its name and color, and then create subcategories that belong to that category, so that later expenses can be filed the way a yogurt purchase is filed under Food and Super/Market.

**Acceptance:**

- A signed-in, verified user can open Categories from the main navigation.
- They can create a category by name and one of the twelve pill colors, and it appears in their list in that color.
- They can update a chosen category's name and color after clicking Edit. The name and color fields stay hidden until then. The category keeps its subcategories. Saving its current name is allowed. A name they already use on another category is shown on the form and is not saved.
- They can delete a chosen category that has no expenses. The trash icon asks for confirmation, then removes that category and its subcategories. A category that still has expenses stays.
- They can create a subcategory by name and one of the twelve pill colors on one of their categories, and it appears under that category in that color.
- They can update a subcategory's name and color after clicking Edit. Saving its current name is allowed. A name that category already uses on another subcategory is shown on the row and is not saved.
- A subcategory color is used for that subcategory's slice on the category rings.
- A subcategory cannot be created without a category, and it cannot be attached to another user's category.
- A guest who opens Categories is redirected to login.
- An empty name, a missing or unknown color, a duplicate category name for that user, or a duplicate subcategory name inside the same category is shown on the form and is not saved.
- They can delete one of their subcategories when it has no expenses. The trash icon asks for confirmation, then removes that subcategory only.

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

## Module: Expenses

A signed-in user records a purchase and sees it in a table with the same columns as the spreadsheet.

<a id="us-exp-01"></a>

### US-EXP-01 — Record an expense

**Status:** Done

**Story:** As a signed-in user, I want an Expenses area where I can record a purchase and see my purchases in a table like the spreadsheet, so that each one keeps its month, type, concept, category, subcategory, and the amount I paid in soles or in dollars.

**Acceptance:**

- A signed-in, verified user can open Expenses from the main navigation.
- They can record an expense with a month, a year, one of their expense types, a concept, and an amount in soles or in dollars. Category and subcategory are optional. Tab moves from concept to amount, and Enter saves the row so the next concept can be typed immediately.
- The new row appears in their table. The amount shows under Soles or under Dólares, and the other currency column is empty. An amount greater than 100 is a yellow pill, greater than 200 is an orange pill, and greater than 500 is a red pill. A missing category or subcategory shows a white `-`. A category with a stored color shows as a pill in that color.
- Year buttons under the introduction choose which year's expenses are shown. A new expense is saved in the selected year. Mes shows the month. The pencil on a row changes that expense's month, type, concept, category, subcategory, currency, and amount in the row, and the type list shows the acronym. The trash icon deletes that expense after confirmation. The form above the table stays for the next new expense.
- A Concepto search above the table matches that column only. Month buttons limit the table to the months that are on; All shows every month. The table shows 25 rows at a time, and Rows can change that to 10, 25, 50, or 100. Previous and Next move between pages.
- A guest who opens Expenses is redirected to login.
- An empty concept, a missing amount, an amount in both currencies, a subcategory that does not belong to the chosen category, another user's category or subcategory, or another user's expense type is shown as an error and is not saved.

## Module: Fixed expenses

A recurring bill, described once, and marked paid for the month being viewed.

<a id="us-fix-01"></a>

### US-FIX-01 — Record a fixed expense

**Status:** Done

**Story:** As a signed-in user, I want a Fixed expenses area where I can describe a recurring bill once and mark it paid for a chosen month, so that Luz, Netflix, or AWS stay out of the variable expenses table and I can see what I still owe this month.

**Acceptance:**

- A signed-in, verified user can open Fixed expenses from the main navigation.
- They can create a bill with a name, one of their expense types, one of their categories, a subcategory of that category, a currency, and a usual amount. The bill appears in every month.
- For a chosen month and year, the table shows their bills. The usual amount shows under Soles or under Dólares.
- They can mark one of those bills paid for that month, with the usual amount or a different amount, and they can clear that paid mark.
- They can edit a bill's name, expense type, category, subcategory, currency, and usual amount. A month already marked paid keeps that month's amount.
- They can delete a bill after confirming. The months already marked paid are removed with it.
- A guest who opens Fixed expenses is redirected to login.
- An empty name, a name that user already has, a missing amount, a subcategory that does not belong to the chosen category, another user's subcategory, or another user's expense type is shown as an error and is not saved.

## Module: General Report

The year view of fixed bills, with variable expenses underneath. Recording a bill still happens in Fixed expenses. Recording a purchase still happens in Expenses.

<a id="us-rpt-01"></a>

### US-RPT-01 — See fixed bills for the year

**Status:** Done

**Story:** As a signed-in user, I want a General Report with the fixed section of my spreadsheet, so that I can see every month of the year with Soles and Dólares.

**Acceptance:**

- A signed-in, verified user can open General Report from the main navigation.
- The table shows the chosen year. Each month has a Soles column and a Dólares column.
- Bills sit under their category, with a total for that category and a total for all fixed bills.
- Every month shows the usual amount. A paid month is green. A month marked paid for a different amount shows that amount and is highlighted.
- A bill in soles leaves Dólares empty, and a bill in dólares leaves Soles empty.
- Below the fixed bills, each category lists its subcategories. Purchases in a subcategory are added together for each month, under Soles or under Dólares. A subcategory with nothing in a month stays empty.
- Each of those categories has a total, and the section has a total for all variable expenses.
- An expense from another year is left out.
- A guest who opens General Report is redirected to login.
- Another user's bills and variable expenses are not included.
