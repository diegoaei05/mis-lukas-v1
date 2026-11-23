import { Form, Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import FixedExpenseController from '@/actions/App/Http/Controllers/FixedExpenseController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as categoriesIndex } from '@/routes/categories';
import { index as expenseTypesIndex } from '@/routes/expense-types';
import { index } from '@/routes/fixed-expenses';

type FixedExpenseRow = {
    id: number;
    name: string;
    type: string;
    category: string;
    subcategory: string;
    amount: string;
    soles: string | null;
    dollars: string | null;
    paidAmount: string | null;
};

type ExpenseTypeOption = {
    id: number;
    acronym: string;
    description: string;
};

type SubcategoryOption = {
    id: number;
    name: string;
};

type CategoryOption = {
    id: number;
    name: string;
    subcategories: SubcategoryOption[];
};

type MonthOption = {
    value: number;
    label: string;
};

type CurrencyOption = {
    value: string;
    label: string;
};

function showPeriod(nextMonth: string, nextYear: string): void {
    router.get(
        index.url({
            query: {
                month: nextMonth,
                year: nextYear,
            },
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

export default function FixedExpensesIndex({
    fixedExpenses,
    expenseTypes,
    categories,
    month,
    year,
    months,
    currencies,
}: {
    fixedExpenses: FixedExpenseRow[];
    expenseTypes: ExpenseTypeOption[];
    categories: CategoryOption[];
    month: number;
    year: number;
    months: MonthOption[];
    currencies: CurrencyOption[];
}) {
    const [expenseTypeId, setExpenseTypeId] = useState(
        expenseTypes[0] ? String(expenseTypes[0].id) : '',
    );
    const [categoryId, setCategoryId] = useState(
        categories[0] ? String(categories[0].id) : '',
    );
    const [subcategoryId, setSubcategoryId] = useState(
        categories[0]?.subcategories[0]
            ? String(categories[0].subcategories[0].id)
            : '',
    );
    const [currency, setCurrency] = useState(currencies[0]?.value ?? 'pen');
    const [periodYear, setPeriodYear] = useState(String(year));

    const selectedCategory =
        categories.find((category) => String(category.id) === categoryId) ??
        null;
    const subcategories = selectedCategory?.subcategories ?? [];

    useEffect(() => {
        setPeriodYear(String(year));
    }, [year]);

    useEffect(() => {
        const options =
            categories.find((category) => String(category.id) === categoryId)
                ?.subcategories ?? [];

        if (
            options.some(
                (subcategory) => String(subcategory.id) === subcategoryId,
            )
        ) {
            return;
        }

        setSubcategoryId(options[0] ? String(options[0].id) : '');
    }, [categories, categoryId, subcategoryId]);

    const canRecord = expenseTypes.length > 0 && categories.length > 0;
    const periodLabel =
        months.find((option) => option.value === month)?.label ?? '';

    return (
        <>
            <Head title="Fixed expenses" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">
                        Fixed expenses
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Describe a bill once, then mark it paid for the month
                        you are looking at.
                    </p>
                </div>

                <div className="flex max-w-xl flex-col gap-4 sm:flex-row sm:items-end">
                    <div className="grid gap-2">
                        <Label htmlFor="fixed-expense-period-month">
                            Month
                        </Label>
                        <Select
                            value={String(month)}
                            onValueChange={(value) =>
                                showPeriod(value, String(year))
                            }
                        >
                            <SelectTrigger
                                id="fixed-expense-period-month"
                                className="w-44"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {months.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={String(option.value)}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="fixed-expense-period-year">Year</Label>
                        <Input
                            id="fixed-expense-period-year"
                            type="number"
                            required
                            min={2000}
                            max={2100}
                            value={periodYear}
                            onChange={(event) =>
                                setPeriodYear(event.target.value)
                            }
                            onBlur={() => {
                                const nextYear = Number(periodYear);

                                if (
                                    Number.isInteger(nextYear) &&
                                    nextYear >= 2000 &&
                                    nextYear <= 2100 &&
                                    nextYear !== year
                                ) {
                                    showPeriod(String(month), String(nextYear));

                                    return;
                                }

                                setPeriodYear(String(year));
                            }}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.currentTarget.blur();
                                }
                            }}
                        />
                    </div>
                </div>

                {canRecord ? (
                    <Form
                        {...FixedExpenseController.store.form()}
                        errorBag="createFixedExpense"
                        resetOnSuccess={['name', 'amount']}
                        disableWhileProcessing
                        className="grid max-w-5xl gap-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="expense_type_id"
                                    value={expenseTypeId}
                                />
                                <input
                                    type="hidden"
                                    name="category_id"
                                    value={categoryId}
                                />
                                <input
                                    type="hidden"
                                    name="subcategory_id"
                                    value={subcategoryId}
                                />
                                <input
                                    type="hidden"
                                    name="currency"
                                    value={currency}
                                />
                                <input
                                    type="hidden"
                                    name="view_month"
                                    value={month}
                                />
                                <input
                                    type="hidden"
                                    name="view_year"
                                    value={year}
                                />

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="fixed-expense-name">
                                        Name
                                    </Label>
                                    <Input
                                        id="fixed-expense-name"
                                        name="name"
                                        required
                                        maxLength={255}
                                        placeholder="Netflix"
                                        data-test="fixed-expense-name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="fixed-expense-type">
                                        Type
                                    </Label>
                                    <Select
                                        value={expenseTypeId}
                                        onValueChange={setExpenseTypeId}
                                    >
                                        <SelectTrigger
                                            id="fixed-expense-type"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {expenseTypes.map((expenseType) => (
                                                <SelectItem
                                                    key={expenseType.id}
                                                    value={String(
                                                        expenseType.id,
                                                    )}
                                                >
                                                    {expenseType.acronym} —{' '}
                                                    {expenseType.description}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.expense_type_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="fixed-expense-currency">
                                        Currency
                                    </Label>
                                    <Select
                                        value={currency}
                                        onValueChange={setCurrency}
                                    >
                                        <SelectTrigger
                                            id="fixed-expense-currency"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Currency" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {currencies.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.currency} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="fixed-expense-category">
                                        Category
                                    </Label>
                                    <Select
                                        value={categoryId}
                                        onValueChange={setCategoryId}
                                    >
                                        <SelectTrigger
                                            id="fixed-expense-category"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Category" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map((category) => (
                                                <SelectItem
                                                    key={category.id}
                                                    value={String(category.id)}
                                                >
                                                    {category.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.category_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="fixed-expense-subcategory">
                                        Subcategory
                                    </Label>
                                    <Select
                                        value={
                                            subcategoryId === ''
                                                ? undefined
                                                : subcategoryId
                                        }
                                        onValueChange={setSubcategoryId}
                                        disabled={subcategories.length === 0}
                                    >
                                        <SelectTrigger
                                            id="fixed-expense-subcategory"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Subcategory" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {subcategories.map(
                                                (subcategory) => (
                                                    <SelectItem
                                                        key={subcategory.id}
                                                        value={String(
                                                            subcategory.id,
                                                        )}
                                                    >
                                                        {subcategory.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.subcategory_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="fixed-expense-amount">
                                        Usual amount
                                    </Label>
                                    <Input
                                        id="fixed-expense-amount"
                                        name="amount"
                                        type="text"
                                        inputMode="decimal"
                                        required
                                        placeholder="45.00"
                                        data-test="fixed-expense-amount"
                                    />
                                    <InputError message={errors.amount} />
                                </div>

                                <div className="flex items-end">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="create-fixed-expense-button"
                                    >
                                        Add bill
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {expenseTypes.length === 0 && (
                            <>
                                <Link
                                    href={expenseTypesIndex()}
                                    className="font-medium text-foreground underline-offset-4 hover:underline"
                                >
                                    Create an expense type
                                </Link>{' '}
                            </>
                        )}
                        {expenseTypes.length === 0 &&
                            categories.length === 0 &&
                            'and '}
                        {categories.length === 0 && (
                            <>
                                <Link
                                    href={categoriesIndex()}
                                    className="font-medium text-foreground underline-offset-4 hover:underline"
                                >
                                    create a category
                                </Link>{' '}
                            </>
                        )}
                        before recording a fixed expense.
                    </p>
                )}

                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full min-w-[52rem] text-sm">
                        <caption className="sr-only">
                            Fixed expenses for {periodLabel} {year}
                        </caption>
                        <thead className="border-b border-border bg-muted/40 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Tipo</th>
                                <th className="px-4 py-3 font-medium">
                                    Categoría
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Subcategoría
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Soles
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Dólares
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Paid
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    <span className="sr-only">Payment</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {fixedExpenses.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-6 text-muted-foreground"
                                    >
                                        No fixed expenses apply to this month.
                                    </td>
                                </tr>
                            ) : (
                                fixedExpenses.map((bill) => (
                                    <tr
                                        key={bill.id}
                                        className="border-b border-border last:border-b-0"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {bill.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {bill.type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {bill.category}
                                        </td>
                                        <td className="px-4 py-3">
                                            {bill.subcategory}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {bill.soles}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {bill.dollars}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {bill.paidAmount}
                                        </td>
                                        <td className="px-4 py-3">
                                            {bill.paidAmount ? (
                                                <Form
                                                    {...FixedExpenseController.destroyPayment.form(
                                                        bill.id,
                                                    )}
                                                    errorBag={`payment-${bill.id}`}
                                                    disableWhileProcessing
                                                    className="flex items-center justify-end"
                                                >
                                                    {({ processing }) => (
                                                        <>
                                                            <input
                                                                type="hidden"
                                                                name="month"
                                                                value={month}
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name="year"
                                                                value={year}
                                                            />
                                                            <Button
                                                                type="submit"
                                                                variant="outline"
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
                                                                data-test={`clear-fixed-expense-${bill.id}`}
                                                            >
                                                                Clear
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            ) : (
                                                <Form
                                                    {...FixedExpenseController.storePayment.form(
                                                        bill.id,
                                                    )}
                                                    errorBag={`payment-${bill.id}`}
                                                    disableWhileProcessing
                                                    className="flex items-start justify-end gap-2"
                                                >
                                                    {({
                                                        errors,
                                                        processing,
                                                    }) => (
                                                        <>
                                                            <input
                                                                type="hidden"
                                                                name="month"
                                                                value={month}
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name="year"
                                                                value={year}
                                                            />
                                                            <div className="grid gap-2">
                                                                <Input
                                                                    name="amount"
                                                                    type="text"
                                                                    inputMode="decimal"
                                                                    required
                                                                    defaultValue={
                                                                        bill.amount
                                                                    }
                                                                    aria-label={`Paid amount for ${bill.name}`}
                                                                    className="w-28"
                                                                    data-test={`pay-fixed-expense-amount-${bill.id}`}
                                                                />
                                                                <InputError
                                                                    message={
                                                                        errors.amount ??
                                                                        errors.month
                                                                    }
                                                                />
                                                            </div>
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
                                                                data-test={`pay-fixed-expense-${bill.id}`}
                                                            >
                                                                Mark paid
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

FixedExpensesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Fixed expenses',
            href: index(),
        },
    ],
};
