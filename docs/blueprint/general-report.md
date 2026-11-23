# General Report

How a signed-in user reads the fixed section of the year. This is the path behind `US-RPT-01`. Bills are created in [fixed expenses](fixed-expenses.md). A paid fixed bill is not written to [expenses](expenses.md).

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
    a year outside 2000–2100 falls back to the current year
    Inertia::render('general-report/index', { groups, totals, year, months })
      resources/js/pages/general-report/index.tsx
```

The page opens on the current year. Changing the year and leaving the field reloads that year. The table is the fixed section: a category band, then each bill, then a Total row, then Total fijos. Every month has a Soles column and a Dólares column. A bill in soles leaves Dólares empty, and the other way around. A paid month has a green cell. A month paid for a different amount than the usual amount is highlighted instead.

This screen does not write to the database.
