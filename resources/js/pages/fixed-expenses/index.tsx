import { Form, Head, Link, router } from '@inertiajs/react';
import { Fragment, useEffect, useState } from 'react';
import FixedExpenseController from '@/actions/App/Http/Controllers/FixedExpenseController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
    expenseTypeId: number;
    category: string;
    categoryId: number;
    subcategory: string;
    subcategoryId: number;
    currency: string;
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

const categoryOrder = ['Vivienda', 'Salud', 'Financieros', 'Otros'];

const billOrder: Record<string, string[]> = {
    Vivienda: ['Renta', 'Celular', 'Internet'],
    Salud: ['San Pablo', 'RIMAC'],
    Financieros: [
        'Transferencia (GF)',
        'Transferencia (LD)',
        'Seguro Tarjeta Interbank',
    ],
    Otros: [
        'Primevideo',
        'Rappi Pro',
        'Cursor',
        'Trello',
        'Smartfit',
        'Pacifico/Carro',
        'Jujitsu',
    ],
};

function sheetIndex(order: string[], name: string): number {
    const index = order.indexOf(name);

    return index === -1 ? Number.MAX_SAFE_INTEGER : index;
}

function groupedBills(bills: FixedExpenseRow[]): {
    category: string;
    bills: FixedExpenseRow[];
}[] {
    const groups = new Map<string, FixedExpenseRow[]>();

    for (const bill of bills) {
        const rows = groups.get(bill.category) ?? [];
        rows.push(bill);
        groups.set(bill.category, rows);
    }

    return [...groups.entries()]
        .sort(([left], [right]) => {
            const leftIndex = sheetIndex(categoryOrder, left);
            const rightIndex = sheetIndex(categoryOrder, right);

            if (leftIndex !== rightIndex) {
                return leftIndex - rightIndex;
            }

            return left.localeCompare(right);
        })
        .map(([category, rows]) => ({
            category,
            bills: [...rows].sort((left, right) => {
                const order = billOrder[category] ?? [];
                const leftIndex = sheetIndex(order, left.name);
                const rightIndex = sheetIndex(order, right.name);

                if (leftIndex !== rightIndex) {
                    return leftIndex - rightIndex;
                }

                return left.name.localeCompare(right.name);
            }),
        }));
}

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
    const [editing, setEditing] = useState<FixedExpenseRow | null>(null);
    const [editExpenseTypeId, setEditExpenseTypeId] = useState('');
    const [editCategoryId, setEditCategoryId] = useState('');
    const [editSubcategoryId, setEditSubcategoryId] = useState('');
    const [editCurrency, setEditCurrency] = useState('pen');

    const selectedCategory =
        categories.find((category) => String(category.id) === categoryId) ??
        null;
    const subcategories = selectedCategory?.subcategories ?? [];
    const editCategory =
        categories.find((category) => String(category.id) === editCategoryId) ??
        null;
    const editSubcategories = editCategory?.subcategories ?? [];

    function openEditor(bill: FixedExpenseRow): void {
        setEditing(bill);
        setEditExpenseTypeId(String(bill.expenseTypeId));
        setEditCategoryId(String(bill.categoryId));
        setEditSubcategoryId(String(bill.subcategoryId));
        setEditCurrency(bill.currency);
    }

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

    useEffect(() => {
        if (editing === null) {
            return;
        }

        const options =
            categories.find((category) => String(category.id) === editCategoryId)
                ?.subcategories ?? [];

        if (
            options.some(
                (subcategory) => String(subcategory.id) === editSubcategoryId,
            )
        ) {
            return;
        }

        setEditSubcategoryId(options[0] ? String(options[0].id) : '');
    }, [categories, editCategoryId, editSubcategoryId, editing]);

    const canRecord = expenseTypes.length > 0 && categories.length > 0;
    const billGroups = groupedBills(fixedExpenses);
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
                            {billGroups.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-6 text-muted-foreground"
                                    >
                                        No fixed expenses apply to this month.
                                    </td>
                                </tr>
                            ) : (
                                billGroups.map((group) => (
                                    <Fragment key={group.category}>
                                        <tr>
                                            <th
                                                colSpan={7}
                                                className="bg-sky-100 px-4 py-2 text-left font-semibold text-sky-950 dark:bg-sky-950 dark:text-sky-100"
                                            >
                                                {group.category}
                                            </th>
                                        </tr>
                                        {group.bills.map((bill) => (
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
                                            <div className="flex items-start justify-end gap-2">
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
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => openEditor(bill)}
                                                data-test={`edit-fixed-expense-${bill.id}`}
                                            >
                                                Edit
                                            </Button>
                                            <Dialog>
                                                <DialogTrigger asChild>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        data-test={`delete-fixed-expense-${bill.id}`}
                                                    >
                                                        Delete
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogTitle>
                                                        Delete {bill.name}?
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        This removes {bill.name}{' '}
                                                        and every month already
                                                        marked paid. You cannot
                                                        undo this.
                                                    </DialogDescription>
                                                    <Form
                                                        {...FixedExpenseController.destroy.form(
                                                            bill.id,
                                                        )}
                                                        disableWhileProcessing
                                                    >
                                                        {({ processing }) => (
                                                            <>
                                                                <input
                                                                    type="hidden"
                                                                    name="view_month"
                                                                    value={
                                                                        month
                                                                    }
                                                                />
                                                                <input
                                                                    type="hidden"
                                                                    name="view_year"
                                                                    value={year}
                                                                />
                                                                <DialogFooter className="gap-2">
                                                                    <DialogClose
                                                                        asChild
                                                                    >
                                                                        <Button
                                                                            type="button"
                                                                            variant="secondary"
                                                                        >
                                                                            Cancel
                                                                        </Button>
                                                                    </DialogClose>
                                                                    <Button
                                                                        type="submit"
                                                                        variant="destructive"
                                                                        disabled={
                                                                            processing
                                                                        }
                                                                        data-test={`confirm-delete-fixed-expense-${bill.id}`}
                                                                    >
                                                                        Delete
                                                                        fixed
                                                                        expense
                                                                    </Button>
                                                                </DialogFooter>
                                                            </>
                                                        )}
                                                    </Form>
                                                </DialogContent>
                                            </Dialog>
                                            </div>
                                        </td>
                                    </tr>
                                        ))}
                                    </Fragment>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Dialog
                    open={editing !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditing(null);
                        }
                    }}
                >
                    <DialogContent>
                        {editing && (
                            <Form
                                key={editing.id}
                                {...FixedExpenseController.update.form(
                                    editing.id,
                                )}
                                errorBag={`updateFixedExpense-${editing.id}`}
                                disableWhileProcessing
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <DialogTitle>
                                            Edit {editing.name}
                                        </DialogTitle>
                                        <DialogDescription>
                                            Change the bill. A month already
                                            marked paid keeps its own amount.
                                        </DialogDescription>
                                        <input
                                            type="hidden"
                                            name="expense_type_id"
                                            value={editExpenseTypeId}
                                        />
                                        <input
                                            type="hidden"
                                            name="category_id"
                                            value={editCategoryId}
                                        />
                                        <input
                                            type="hidden"
                                            name="subcategory_id"
                                            value={editSubcategoryId}
                                        />
                                        <input
                                            type="hidden"
                                            name="currency"
                                            value={editCurrency}
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
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-fixed-expense-name">
                                                Name
                                            </Label>
                                            <Input
                                                id="edit-fixed-expense-name"
                                                name="name"
                                                required
                                                maxLength={255}
                                                defaultValue={editing.name}
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-fixed-expense-type">
                                                Type
                                            </Label>
                                            <Select
                                                value={editExpenseTypeId}
                                                onValueChange={
                                                    setEditExpenseTypeId
                                                }
                                            >
                                                <SelectTrigger
                                                    id="edit-fixed-expense-type"
                                                    className="w-full"
                                                >
                                                    <SelectValue placeholder="Type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {expenseTypes.map(
                                                        (expenseType) => (
                                                            <SelectItem
                                                                key={
                                                                    expenseType.id
                                                                }
                                                                value={String(
                                                                    expenseType.id,
                                                                )}
                                                            >
                                                                {
                                                                    expenseType.acronym
                                                                }{' '}
                                                                —{' '}
                                                                {
                                                                    expenseType.description
                                                                }
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    errors.expense_type_id
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-fixed-expense-currency">
                                                Currency
                                            </Label>
                                            <Select
                                                value={editCurrency}
                                                onValueChange={setEditCurrency}
                                            >
                                                <SelectTrigger
                                                    id="edit-fixed-expense-currency"
                                                    className="w-full"
                                                >
                                                    <SelectValue placeholder="Currency" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {currencies.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.currency}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-fixed-expense-category">
                                                Category
                                            </Label>
                                            <Select
                                                value={editCategoryId}
                                                onValueChange={
                                                    setEditCategoryId
                                                }
                                            >
                                                <SelectTrigger
                                                    id="edit-fixed-expense-category"
                                                    className="w-full"
                                                >
                                                    <SelectValue placeholder="Category" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {categories.map(
                                                        (category) => (
                                                            <SelectItem
                                                                key={
                                                                    category.id
                                                                }
                                                                value={String(
                                                                    category.id,
                                                                )}
                                                            >
                                                                {category.name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.category_id}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-fixed-expense-subcategory">
                                                Subcategory
                                            </Label>
                                            <Select
                                                value={
                                                    editSubcategoryId === ''
                                                        ? undefined
                                                        : editSubcategoryId
                                                }
                                                onValueChange={
                                                    setEditSubcategoryId
                                                }
                                                disabled={
                                                    editSubcategories.length ===
                                                    0
                                                }
                                            >
                                                <SelectTrigger
                                                    id="edit-fixed-expense-subcategory"
                                                    className="w-full"
                                                >
                                                    <SelectValue placeholder="Subcategory" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {editSubcategories.map(
                                                        (subcategory) => (
                                                            <SelectItem
                                                                key={
                                                                    subcategory.id
                                                                }
                                                                value={String(
                                                                    subcategory.id,
                                                                )}
                                                            >
                                                                {
                                                                    subcategory.name
                                                                }
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    errors.subcategory_id
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-fixed-expense-amount">
                                                Usual amount
                                            </Label>
                                            <Input
                                                id="edit-fixed-expense-amount"
                                                name="amount"
                                                type="text"
                                                inputMode="decimal"
                                                required
                                                defaultValue={editing.amount}
                                            />
                                            <InputError
                                                message={errors.amount}
                                            />
                                        </div>
                                        <DialogFooter>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Save bill
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        )}
                    </DialogContent>
                </Dialog>
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
