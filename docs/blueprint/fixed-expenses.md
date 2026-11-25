# Fixed expenses

How a signed-in user describes a recurring bill once and marks it paid for a chosen month. This is the path behind `US-FIX-01`.

The shared shape of a screen is in [blueprint.md](../blueprint.md). Tipo comes from [expense types](expense-types.md). The category and subcategory come from [categories](categories.md). A paid fixed bill is not written to [expenses](expenses.md).

```text
GET /fixed-expenses?month={month}&year={year}
  auth, verified
  FixedExpenseController::index
    the user's bills, ordered by name, because every bill applies to every month
    each row carries the type acronym, category name, subcategory name, usual amount under soles or dollars, and the paid amount for that month when it exists
    a month outside 1–12 or a year outside 2000–2100 falls back to the current month and year
    Inertia::render('fixed-expenses/index', { fixedExpenses, expenseTypes, categories, month, year, months, currencies })
      resources/js/pages/fixed-expenses/index.tsx
```

The page opens on the current month. Choosing a month reloads that month immediately. Changing the year and leaving the field reloads that year. Every bill appears in every month, under a band for its category, in the same order as the general report. The table lists Name, Tipo, Subcategoría, Soles, Dólares, and Paid. A form adds a bill. Edit, next to Mark paid or Clear, changes that bill. Delete asks for confirmation, then removes the bill and every month already marked paid.

```text
POST /fixed-expenses
  StoreFixedExpenseRequest
    trim the name
    treat a comma decimal such as 45,00 as 45.00
    name is required, at most 255 characters, and unique for this user including letter case
    currency is pen or usd
    amount is greater than zero, with at most two decimal places
    the expense type and the category must belong to this user, or the response is 404
    the subcategory must belong to this user, or the response is 404
    the subcategory must belong to the selected category
    INSERT INTO fixed_expenses

PUT /fixed-expenses/{fixedExpense}
  UpdateFixedExpenseRequest
    the bill must belong to this user, or the response is 404
    the same rules as creating a bill
    the current name is allowed, including a change of letter case
    a paid month is left as it is
    UPDATE fixed_expenses

DELETE /fixed-expenses/{fixedExpense}
  the bill must belong to this user, or the response is 404
  DELETE FROM fixed_expenses
  payments for that bill are removed with it

POST /fixed-expenses/{fixedExpense}/payments
  the bill must belong to this user, or the response is 404
  the month must not already be paid
  INSERT INTO fixed_expense_payments

DELETE /fixed-expenses/{fixedExpense}/payments
  the bill must belong to this user, or the response is 404
  DELETE that month's payment
  the bill remains
```

The create form posts through Wayfinder and uses the `createFixedExpense` error bag. The edit dialog uses `updateFixedExpense-{id}`. Each row's payment form uses `payment-{id}`. Delete posts through Wayfinder after confirmation. A successful create, edit, delete, or payment change redirects to `fixed-expenses.index` for the month being viewed and flashes a toast.

`name`, `currency`, and `amount` are the only fillable attributes on the bill. `user_id`, `expense_type_id`, and `subcategory_id` are set from the signed-in user and the chosen type and subcategory. The paid amount is stored on the payment, so a different amount for one month leaves the usual amount unchanged.

Deleting the user deletes their fixed expenses and their payments. An expense type or a subcategory that still has a fixed expense cannot be deleted.

| Table                    | Columns written by this module                                               |
| ------------------------ | ---------------------------------------------------------------------------- |
| `fixed_expenses`         | `user_id`, `expense_type_id`, `subcategory_id`, `name`, `currency`, `amount` |
| `fixed_expense_payments` | `fixed_expense_id`, `year`, `month`, `amount`                                |
