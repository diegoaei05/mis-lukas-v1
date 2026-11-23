# User stories

The product description is in [overview.md](overview.md).

Index of what this app must do. A feature is finished only when every acceptance criterion on its story is true. Full text lives in the linked files, not here.

- [Completed](user-stories/completed.md) — behavior already in the app. Story and acceptance only.
- [New](user-stories/new/) — one file per story still to build. Each file has a restatement and a plan of action. Nothing there is built until its status is `Agreed`.

When a new story is finished, add a short copy (story and acceptance, status `Done`) to [completed.md](user-stories/completed.md), delete its file under `user-stories/new/`, and move the row below. Record how the screen actually works in [blueprint.md](blueprint.md).

## How a new story is added

1. You describe the story in plain language.
2. I create `docs/user-stories/new/US-XXX-00-short-title.md` with status `Needs confirmation`, your words, a restatement, any assumption, a plan of action, and acceptance criteria. I add a row to the table below.
3. You confirm the restatement or correct it.
4. Status becomes `Agreed`. That is the signal to implement the plan.
5. Status becomes `In progress` while the code and tests are being written, then the story moves to Completed.

Do not start implementation while status is `Needs confirmation`.

Each new file contains: **ID**, **Status** (`Needs confirmation`, `Agreed`, or `In progress`), **Your words**, **Restatement**, **Assumptions**, **Plan of action** (migration, model, route, controller, Inertia page, Wayfinder form, tests), and **Acceptance**.

## Completed

| ID                                               | Summary                                                            | Status |
| ------------------------------------------------ | ------------------------------------------------------------------ | ------ |
| [US-ACC-01](user-stories/completed.md#us-acc-01) | A visitor can open the registration page.                          | Done   |
| [US-ACC-02](user-stories/completed.md#us-acc-02) | A visitor can submit name, email, and password and get an account. | Done   |
| [US-ACC-03](user-stories/completed.md#us-acc-03) | Invalid registration input stays on the form with field errors.    | Done   |

## New

| ID | Summary | Status |
| --- | --- | --- |
| [US-CAT-01](user-stories/new/US-CAT-01-create-category-and-subcategories.md) | A signed-in user can create a category and add subcategories that belong to it. | Needs confirmation |
