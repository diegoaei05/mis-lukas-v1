# General Report

How a signed-in user reads the year: fixed bills, then variable expenses underneath. This is the path behind `US-RPT-01`. Bills are created in [fixed expenses](fixed-expenses.md). Purchases are recorded in [expenses](expenses.md). A paid fixed bill is not written to the expenses table.

The shared shape of a screen is in [blueprint.md](../blueprint.md).

```text
GET /general-report?year={year}
  auth, verified
  GeneralReportController::index
    the user's bills for the whole year, grouped in sheet order: Vivienda, Salud, Financieros, Otros, then any other category by name
    bills inside a category follow that same sheet order, then any other bill by name
    each bill has twelve month cells; the usual amount fills every month, and a payment for that month replaces it
    a month with a payment is marked paid, and a payment that differs from the usual amount is marked changed
    each category has a Soles total and a Dólares total per month, and the page has the same totals for every bill
    below that, every category the user has, in sheet order: PKYTP, Vivienda, Financieros, Comida, Otros, then any other category by name
    each subcategory is a row under its category, in that same sheet order, then any other subcategory by name
    purchases in a subcategory are added together for each month; a month with none stays empty
    each of those categories has a total, and the page has a total for every variable expense
    an expense from another year is left out
    a year outside 2000–2100 falls back to the current year
    Inertia::render('general-report/index', { groups, totals, variableGroups, variableTotals, year, months })
      resources/js/pages/general-report/index.tsx
```

The page opens on the current year. Changing the year and leaving the field reloads that year. The table starts with a Fijos band, then a category band, each bill, a Total row, and Total fijos. Under that, a Variables band, then each category with its subcategories, a Total for the category, and Total variables. A category with a stored color shows as a pill in that color. A category with no color is plain text. Every month has a Soles column and a Dólares column. A bill in soles leaves Dólares empty, and the other way around. A paid fixed month has a green cell. A fixed month paid for a different amount than the usual amount is highlighted instead. A subcategory shows the sum of that year's purchases in each month, and a month with none stays empty.

This screen does not write to the database.
