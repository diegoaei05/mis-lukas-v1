# US-EXP-01 — Record an expense

**Status:** Needs confirmation

## Your words

The next user story is to add the expenses. The spreadsheet is how expenses are kept today, and the module should work in a similar way.

The sheet is a table. Each row is one expense:

| Mes   | Tipo | Concepto                    | Categoría | Subcategoría       | Soles  | Dólares |
| ----- | ---- | --------------------------- | --------- | ------------------ | ------ | ------- |
| Enero | IO   | Koanna - Rest               | Comida    | Restaurantes/bares | 47.00  |         |
| Enero | IO   | Trattoria - Rest            | Comida    | Restaurantes/bares | 68.00  |         |
| Enero | IO   | Uber                        | Otros     | Taxis              | 12.90  |         |
| Enero | IO   | Uber                        | Otros     | Taxis              | 11.90  |         |
| Enero | EF   | Viaje al sur gastos comunes | Otros     | Viajes             | 153.25 |         |

## Restatement

As a signed-in user, I want an Expenses area where I can record a purchase and see my purchases in a table like the spreadsheet, so that each one keeps its month, type, concept, category, subcategory, and the amount I paid in soles or in dollars.

## Assumptions

These are the choices I am making until you correct them.

- One expense is one row. It belongs to you, and you only see your own rows.
- The table columns are the ones visible in the sheet: Mes, Tipo, Concepto, Categoría, Subcategoría, Soles, and Dólares. Columns hidden between Subcategoría and Soles are ignored.
- Mes is a calendar month, shown in Spanish (`Enero` … `Diciembre`). The year is stored with it so a later January does not mix with this one. The table shows the month name, the same way the sheet does.
- Tipo is an expense type you already created in [US-TYP-01](US-TYP-01-create-an-expense-type.md). The sheet's `IO` is the acronym whose description is `IO credit card`, and `EF` is the acronym whose description is `Efectivo`. The expenses table shows the acronym. This story is not built until expense types exist. The expenses form cannot invent a type that is not yours.
- Concepto is a short description, required, at most 255 characters. Two rows can share the same concept, as the two Uber rows do.
- Categoría and Subcategoría must already exist on your account. The subcategory has to belong to the category you picked. `Comida` / `Restaurantes/bares`, `Otros` / `Taxis`, and `Otros` / `Viajes` are examples already in the category list.
- Each row is paid in one currency. You fill Soles or Dólares, not both, and not neither. The amount is greater than zero and keeps two decimal places.
- This story adds a row and lists the rows. It does not edit a row, delete a row, total a month, filter the table, or import the existing spreadsheet.

## Plan of action

1. **Migration.** An `expenses` table: `user_id`, `expense_type_id`, `subcategory_id`, `month` (1–12), `year`, `concept`, `currency` (`pen` or `usd`), `amount` as a decimal with two places, and timestamps. The category is not stored again, because the subcategory already belongs to one category. The acronym is not stored again, because the expense type already has it. Deleting the user deletes their expenses. A subcategory or an expense type that still has expenses cannot be deleted.

2. **Model.** `Expense` belongs to a user, an expense type, and a subcategory. `User` has many expenses. Fillable attributes are month, year, concept, currency, and amount. `user_id`, `expense_type_id`, and `subcategory_id` are set from the signed-in user and the chosen type and subcategory, not from extra request fields.

3. **Route.** Behind `auth` and `verified`: `GET /expenses` named `expenses.index`, and `POST /expenses` named `expenses.store`.

4. **Controller.** `ExpenseController@index` loads the user's expenses, newest first, with the expense type acronym, category name, and subcategory name, plus the user's expense types and categories with their subcategories for the form. It renders `expenses/index`. `store` checks that the expense type belongs to the user and that the subcategory belongs to the user and to the selected category, creates the row, and redirects back to the list with a toast.

5. **Inertia page.** `resources/js/pages/expenses/index.tsx`, linked from the main navigation. A table with the seven columns. Tipo shows the acronym. Soles and Dólares each show the amount only when that row used that currency. Above the table, a form: month, year, expense type, concept, category, subcategory (limited to the selected category), and one amount with a currency choice.

6. **Wayfinder form.** The page posts with the generated `store.form()` helper for `POST /expenses`. Validation errors stay on that form.

7. **Tests.** A guest is sent to login. An unverified user is sent to the verification notice. A signed-in user can save a soles expense and a dollars expense and see them in the table, with the type's acronym in Tipo. A row with both currencies, neither currency, a zero amount, a blank concept, a subcategory from another category, or an expense type that is not theirs is rejected and not saved. Another user's subcategory or expense type is a 404. The list does not include another user's expenses.

## Acceptance

- A signed-in, verified user can open Expenses from the main navigation.
- They can record an expense with a month, a year, one of their expense types, a concept, one of their categories, a subcategory of that category, and an amount in soles or in dollars.
- The new row appears in their table. The amount shows under Soles or under Dólares, and the other currency column is empty.
- A guest who opens Expenses is redirected to login.
- An empty concept, a missing amount, an amount in both currencies, a subcategory that does not belong to the chosen category, another user's subcategory, or another user's expense type is shown as an error and is not saved.
