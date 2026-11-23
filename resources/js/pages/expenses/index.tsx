import { Form, Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
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
import { index } from '@/routes/expenses';

type ExpenseRow = {
    id: number;
    month: string;
    year: number;
    type: string;
    concept: string;
    category: string;
    subcategory: string;
    soles: string | null;
    dollars: string | null;
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

export default function ExpensesIndex({
    expenses,
    expenseTypes,
    categories,
    months,
    currencies,
}: {
    expenses: ExpenseRow[];
    expenseTypes: ExpenseTypeOption[];
    categories: CategoryOption[];
    months: MonthOption[];
    currencies: CurrencyOption[];
}) {
    const [month, setMonth] = useState(String(new Date().getMonth() + 1));
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

    const selectedCategory =
        categories.find((category) => String(category.id) === categoryId) ??
        null;
    const subcategories = selectedCategory?.subcategories ?? [];

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

    return (
        <>
            <Head title="Expenses" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">
                        Expenses
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Record a purchase and keep it with its month, type,
                        category, and the amount you paid.
                    </p>
                </div>

                {canRecord ? (
                    <Form
                        {...ExpenseController.store.form()}
                        errorBag="createExpense"
                        resetOnSuccess={['concept', 'amount']}
                        disableWhileProcessing
                        className="grid max-w-5xl gap-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input type="hidden" name="month" value={month} />
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

                                <div className="grid gap-2">
                                    <Label htmlFor="expense-month">Month</Label>
                                    <Select
                                        value={month}
                                        onValueChange={setMonth}
                                    >
                                        <SelectTrigger
                                            id="expense-month"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Month" />
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
                                    <InputError message={errors.month} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="expense-year">Year</Label>
                                    <Input
                                        id="expense-year"
                                        name="year"
                                        type="number"
                                        required
                                        min={2000}
                                        max={2100}
                                        defaultValue={new Date().getFullYear()}
                                        data-test="expense-year"
                                    />
                                    <InputError message={errors.year} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="expense-type">Type</Label>
                                    <Select
                                        value={expenseTypeId}
                                        onValueChange={setExpenseTypeId}
                                    >
                                        <SelectTrigger
                                            id="expense-type"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {expenseTypes.map((expenseType) => (
                                                <SelectItem
                                                    key={expenseType.id}
                                                    value={String(expenseType.id)}
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
                                    <Label htmlFor="expense-currency">
                                        Currency
                                    </Label>
                                    <Select
                                        value={currency}
                                        onValueChange={setCurrency}
                                    >
                                        <SelectTrigger
                                            id="expense-currency"
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

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="expense-concept">
                                        Concept
                                    </Label>
                                    <Input
                                        id="expense-concept"
                                        name="concept"
                                        required
                                        maxLength={255}
                                        placeholder="Koanna - Rest"
                                        data-test="expense-concept"
                                    />
                                    <InputError message={errors.concept} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="expense-category">
                                        Category
                                    </Label>
                                    <Select
                                        value={categoryId}
                                        onValueChange={setCategoryId}
                                    >
                                        <SelectTrigger
                                            id="expense-category"
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
                                    <Label htmlFor="expense-subcategory">
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
                                            id="expense-subcategory"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Subcategory" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {subcategories.map((subcategory) => (
                                                <SelectItem
                                                    key={subcategory.id}
                                                    value={String(
                                                        subcategory.id,
                                                    )}
                                                >
                                                    {subcategory.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.subcategory_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="expense-amount">
                                        Amount
                                    </Label>
                                    <Input
                                        id="expense-amount"
                                        name="amount"
                                        type="text"
                                        inputMode="decimal"
                                        required
                                        placeholder="47.00"
                                        data-test="expense-amount"
                                    />
                                    <InputError message={errors.amount} />
                                </div>

                                <div className="flex items-end">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="create-expense-button"
                                    >
                                        Add expense
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
                        before recording an expense.
                    </p>
                )}

                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <table className="w-full min-w-[44rem] text-sm">
                        <caption className="sr-only">Your expenses</caption>
                        <thead className="border-b border-border bg-muted/40 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Mes</th>
                                <th className="px-4 py-3 font-medium">Tipo</th>
                                <th className="px-4 py-3 font-medium">
                                    Concepto
                                </th>
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
                            </tr>
                        </thead>
                        <tbody>
                            {expenses.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-6 text-muted-foreground"
                                    >
                                        You have not recorded any expenses yet.
                                    </td>
                                </tr>
                            ) : (
                                expenses.map((expense) => (
                                    <tr
                                        key={expense.id}
                                        className="border-b border-border last:border-b-0"
                                    >
                                        <td className="px-4 py-3">
                                            {expense.month}{' '}
                                            <span className="text-muted-foreground">
                                                {expense.year}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 font-medium">
                                            {expense.type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {expense.concept}
                                        </td>
                                        <td className="px-4 py-3">
                                            {expense.category}
                                        </td>
                                        <td className="px-4 py-3">
                                            {expense.subcategory}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {expense.soles}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {expense.dollars}
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

ExpensesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Expenses',
            href: index(),
        },
    ],
};
