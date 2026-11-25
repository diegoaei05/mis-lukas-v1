import { Form, Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import ExpenseController, { update } from '@/actions/App/Http/Controllers/ExpenseController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CategoryPill } from '@/lib/category-colors';
import { cn } from '@/lib/utils';
import { index as expenseTypesIndex } from '@/routes/expense-types';
import { index } from '@/routes/expenses';

type ExpenseRow = {
    id: number;
    month: string;
    monthValue: number;
    year: number;
    type: string;
    typeId: number;
    concept: string;
    category: string | null;
    categoryId: number | null;
    categoryColor: string | null;
    subcategory: string | null;
    subcategoryId: number | null;
    currency: string;
    amount: string;
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
    short: string;
};

type ExpenseFilters = {
    concept: string;
    months: number[];
    perPage: number;
};

type ExpensePagination = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    from: number | null;
    to: number | null;
};

type CurrencyOption = {
    value: string;
    label: string;
};

const emptyChoice = 'none';

function amountFill(amount: string | null): string {
    if (amount === null || amount === '') {
        return '';
    }

    const value = Number(amount);

    if (Number.isNaN(value)) {
        return '';
    }

    if (value > 500) {
        return 'bg-amount-500 text-amount-500-foreground';
    }

    if (value > 200) {
        return 'bg-amount-200 text-amount-foreground';
    }

    if (value > 100) {
        return 'bg-amount-100 text-amount-foreground';
    }

    return '';
}

function AmountPill({ amount }: { amount: string | null }) {
    if (amount === null || amount === '') {
        return null;
    }

    const fill = amountFill(amount);

    if (fill === '') {
        return <>{amount}</>;
    }

    return <span className={cn('inline-block rounded-full px-2.5 py-0.5 tabular-nums', fill)}>{amount}</span>;
}

function RowSelect({ id, value, onValueChange, options, disabled = false, error, emptyLabel, className = 'w-full min-w-0' }: { id: string; value: string; onValueChange: (value: string) => void; options: { value: string; label: string }[]; disabled?: boolean; error?: string; emptyLabel?: string; className?: string }) {
    return (
        <div className={`grid min-w-0 gap-1 overflow-hidden ${className}`}>
            <Select value={value === '' ? emptyChoice : value} onValueChange={(next) => onValueChange(next === emptyChoice ? '' : next)} disabled={disabled}>
                <SelectTrigger id={id} className="h-8 w-full">
                    <SelectValue placeholder={emptyLabel ?? 'Select'} />
                </SelectTrigger>
                <SelectContent>
                    {emptyLabel && <SelectItem value={emptyChoice}>{emptyLabel}</SelectItem>}
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}

function AmountEditor({ id, amount, error, onAmount }: { id: string; amount: string; error?: string; onAmount: (amount: string) => void }) {
    return (
        <div className="grid w-full min-w-0 gap-1">
            <Input id={id} value={amount} inputMode="decimal" onChange={(event) => onAmount(event.target.value)} className="h-8 text-right" />
            <InputError message={error} />
        </div>
    );
}

export default function ExpensesIndex({ year, years, expenses, filters, pagination, perPageOptions, expenseTypes, categories, months, currencies }: { year: number; years: number[]; expenses: ExpenseRow[]; filters: ExpenseFilters; pagination: ExpensePagination; perPageOptions: number[]; expenseTypes: ExpenseTypeOption[]; categories: CategoryOption[]; months: MonthOption[]; currencies: CurrencyOption[] }) {
    const [month, setMonth] = useState(String(new Date().getMonth() + 1));
    const [expenseTypeId, setExpenseTypeId] = useState(expenseTypes[0] ? String(expenseTypes[0].id) : '');
    const [categoryId, setCategoryId] = useState('');
    const [subcategoryId, setSubcategoryId] = useState('');
    const [currency, setCurrency] = useState(currencies[0]?.value ?? 'pen');
    const conceptRef = useRef<HTMLInputElement>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [conceptDraft, setConceptDraft] = useState(filters.concept);

    function listQuery(overrides: { year?: number; concept?: string; months?: number[]; per_page?: number; page?: number } = {}) {
        const nextConcept = overrides.concept ?? filters.concept;
        const nextMonths = overrides.months ?? filters.months;
        const nextPerPage = overrides.per_page ?? filters.perPage;
        const nextPage = overrides.page;

        return {
            year: overrides.year ?? year,
            ...(nextConcept !== '' ? { concept: nextConcept } : {}),
            ...(nextMonths.length > 0 ? { months: nextMonths } : {}),
            ...(nextPerPage !== 25 ? { per_page: nextPerPage } : {}),
            ...(nextPage !== undefined && nextPage > 1 ? { page: nextPage } : {}),
        };
    }

    const requestedConcept = useRef(filters.concept);
    const hasFilters = filters.concept !== '' || filters.months.length > 0;

    function visit(overrides: { year?: number; concept?: string; months?: number[]; per_page?: number; page?: number }) {
        requestedConcept.current = overrides.concept ?? filters.concept;
        router.get(index.url({ query: listQuery(overrides) }), {}, { preserveScroll: true, preserveState: true, replace: true });
    }

    useEffect(() => {
        const nextConcept = conceptDraft.trim();

        if (nextConcept === filters.concept || nextConcept === requestedConcept.current) {
            return;
        }

        const timeout = window.setTimeout(() => {
            visit({ concept: nextConcept });
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [conceptDraft, filters.concept, filters.months, filters.perPage, year]);
    const filing = useForm({
        month: '',
        year: '',
        expense_type_id: '',
        category_id: '',
        subcategory_id: '',
        concept: '',
        currency: 'pen',
        amount: '',
    });

    function openRowEditor(expense: ExpenseRow): void {
        setEditingId(expense.id);
        filing.clearErrors();
        filing.setData({
            month: String(expense.monthValue),
            year: String(expense.year),
            expense_type_id: String(expense.typeId),
            category_id: expense.categoryId === null ? '' : String(expense.categoryId),
            subcategory_id: expense.subcategoryId === null ? '' : String(expense.subcategoryId),
            concept: expense.concept,
            currency: expense.currency,
            amount: expense.amount,
        });
    }

    function changeFilingCategory(value: string): void {
        const options = categories.find((category) => String(category.id) === value)?.subcategories ?? [];

        filing.setData((current) => ({
            ...current,
            category_id: value,
            subcategory_id: options.some((subcategory) => String(subcategory.id) === current.subcategory_id) ? current.subcategory_id : '',
        }));
    }

    function saveFiling(expenseId: number): void {
        filing.put(update.url(expenseId), {
            errorBag: `updateExpense-${expenseId}`,
            preserveScroll: true,
            onSuccess: () => setEditingId(null),
        });
    }

    function cancelFiling(): void {
        setEditingId(null);
        filing.clearErrors();
    }

    const selectedCategory = categories.find((category) => String(category.id) === categoryId) ?? null;
    const subcategories = selectedCategory?.subcategories ?? [];

    useEffect(() => {
        const options = categories.find((category) => String(category.id) === categoryId)?.subcategories ?? [];

        if (subcategoryId === '' || options.some((subcategory) => String(subcategory.id) === subcategoryId)) {
            return;
        }

        setSubcategoryId('');
    }, [categories, categoryId, subcategoryId]);

    const canRecord = expenseTypes.length > 0;

    return (
        <>
            <Head title="Expenses" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-0.5">
                        <h2 className="text-xl font-semibold tracking-tight">Expenses</h2>
                        <p className="text-sm text-muted-foreground">Record a purchase, then review and filter the year below.</p>
                    </div>

                    <div className="flex flex-wrap gap-2" role="group" aria-label="Year">
                        {years.map((option) => (
                            <Button key={option} variant={option === year ? 'default' : 'outline'} size="sm" asChild>
                                <Link href={index({ query: listQuery({ year: option }) })} preserveScroll aria-current={option === year ? 'page' : undefined}>
                                    {option}
                                </Link>
                            </Button>
                        ))}
                    </div>
                </div>

                <section className="rounded-xl border border-border bg-card" aria-labelledby="new-expense-heading">
                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                        <span className="flex size-6 items-center justify-center rounded-md bg-emerald-600 text-white">
                            <Plus className="size-4" />
                        </span>
                        <h3 id="new-expense-heading" className="text-sm font-semibold">
                            New expense
                        </h3>
                        <span className="text-xs text-muted-foreground">· saved in {year}</span>
                    </div>
                    <div className="p-4">
                        {canRecord ? (
                            <Form
                                {...ExpenseController.store.form()}
                                errorBag="createExpense"
                                resetOnSuccess={['concept', 'amount']}
                                disableWhileProcessing
                                onSuccess={() => {
                                    window.setTimeout(() => {
                                        conceptRef.current?.focus();
                                    }, 0);
                                }}
                                className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[repeat(5,minmax(0,1fr))_auto]"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <input type="hidden" name="month" value={month} />
                                        <input type="hidden" name="year" value={year} />
                                        <input type="hidden" name="expense_type_id" value={expenseTypeId} />
                                        <input type="hidden" name="category_id" value={categoryId} />
                                        <input type="hidden" name="subcategory_id" value={subcategoryId} />
                                        <input type="hidden" name="currency" value={currency} />

                                        <div className="grid gap-2 sm:col-span-2 lg:col-span-3">
                                            <Label htmlFor="expense-concept">Concept</Label>
                                            <Input ref={conceptRef} id="expense-concept" name="concept" required autoFocus maxLength={255} placeholder="Koanna - Rest" data-test="expense-concept" />
                                            <InputError message={errors.concept} />
                                        </div>

                                        <div className="grid gap-2 lg:col-span-2">
                                            <Label htmlFor="expense-amount">Amount</Label>
                                            <Input id="expense-amount" name="amount" type="text" inputMode="decimal" required placeholder="47.00" data-test="expense-amount" />
                                            <InputError message={errors.amount} />
                                        </div>

                                        <div className="flex items-end">
                                            <Button type="submit" disabled={processing} data-test="create-expense-button">
                                                Add expense
                                            </Button>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="expense-month">Month</Label>
                                            <Select value={month} onValueChange={setMonth}>
                                                <SelectTrigger id="expense-month" className="w-full">
                                                    <SelectValue placeholder="Month" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {months.map((option) => (
                                                        <SelectItem key={option.value} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.month} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="expense-type">Type</Label>
                                            <Select value={expenseTypeId} onValueChange={setExpenseTypeId}>
                                                <SelectTrigger id="expense-type" className="w-full">
                                                    <SelectValue placeholder="Type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {expenseTypes.map((expenseType) => (
                                                        <SelectItem key={expenseType.id} value={String(expenseType.id)}>
                                                            {expenseType.acronym} — {expenseType.description}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.expense_type_id} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="expense-currency">Currency</Label>
                                            <Select value={currency} onValueChange={setCurrency}>
                                                <SelectTrigger id="expense-currency" className="w-full">
                                                    <SelectValue placeholder="Currency" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {currencies.map((option) => (
                                                        <SelectItem key={option.value} value={option.value}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.currency} />
                                        </div>

                                        <div className="grid gap-2 sm:col-start-1 lg:col-start-auto">
                                            <Label htmlFor="expense-category">
                                                Category
                                                <span className="font-normal text-muted-foreground"> (optional)</span>
                                            </Label>
                                            <Select
                                                value={categoryId === '' ? emptyChoice : categoryId}
                                                onValueChange={(value) => {
                                                    setCategoryId(value === emptyChoice ? '' : value);
                                                }}
                                            >
                                                <SelectTrigger id="expense-category" className="w-full">
                                                    <SelectValue placeholder="Optional" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value={emptyChoice}>Optional</SelectItem>
                                                    {categories.map((category) => (
                                                        <SelectItem key={category.id} value={String(category.id)}>
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
                                                <span className="font-normal text-muted-foreground"> (optional)</span>
                                            </Label>
                                            <Select
                                                value={subcategoryId === '' ? emptyChoice : subcategoryId}
                                                onValueChange={(value) => {
                                                    setSubcategoryId(value === emptyChoice ? '' : value);
                                                }}
                                                disabled={categoryId === ''}
                                            >
                                                <SelectTrigger id="expense-subcategory" className="w-full">
                                                    <SelectValue placeholder="Optional" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value={emptyChoice}>Optional</SelectItem>
                                                    {subcategories.map((subcategory) => (
                                                        <SelectItem key={subcategory.id} value={String(subcategory.id)}>
                                                            {subcategory.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.subcategory_id} />
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                <Link href={expenseTypesIndex()} className="font-medium text-foreground underline-offset-4 hover:underline">
                                    Create an expense type
                                </Link>{' '}
                                before recording an expense.
                            </p>
                        )}
                    </div>
                </section>

                <section className="rounded-xl border border-border bg-card" aria-labelledby="expense-list-heading">
                    <div className="flex flex-col gap-3 border-b border-border px-4 py-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <h3 id="expense-list-heading" className="text-sm font-semibold">
                                {year} expenses <span className="font-normal text-muted-foreground">· {pagination.total} shown</span>
                            </h3>
                            <div className="relative w-full sm:w-80">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input id="expense-search" value={conceptDraft} onChange={(event) => setConceptDraft(event.target.value)} placeholder="Search concepto" aria-label="Search concepto" className="px-9" data-test="expense-search" />
                                {conceptDraft !== '' && (
                                    <button type="button" onClick={() => setConceptDraft('')} className="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded-sm text-muted-foreground hover:text-foreground" aria-label="Clear search">
                                        <X className="size-4" />
                                    </button>
                                )}
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-1.5" role="group" aria-label="Months">
                            <span className="mr-1 text-xs text-muted-foreground">Months</span>
                            <MonthChip active={filters.months.length === 0} onClick={() => visit({ months: [] })}>
                                All
                            </MonthChip>
                            {months.map((option) => (
                                <MonthChip key={option.value} active={filters.months.includes(option.value)} onClick={() => visit({ months: filters.months.includes(option.value) ? filters.months.filter((month) => month !== option.value) : [...filters.months, option.value].sort((left, right) => left - right) })}>
                                    {option.short}
                                </MonthChip>
                            ))}
                            {hasFilters && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setConceptDraft('');
                                        visit({ concept: '', months: [] });
                                    }}
                                    className="ml-auto text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground"
                                    data-test="clear-expense-filters"
                                >
                                    Clear filters
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full table-fixed text-sm [&_td]:break-words [&_th]:break-words">
                            <caption className="sr-only">Your expenses</caption>
                            <colgroup>
                                <col className="w-[12%]" />
                                <col className="w-[7%]" />
                                <col className="w-[24%]" />
                                <col className="w-[12%]" />
                                <col className="w-[17%]" />
                                <col className="w-[8%]" />
                                <col className="w-[8%]" />
                                <col className="w-[12%]" />
                            </colgroup>
                            <thead className="border-b border-border bg-muted/40 text-left">
                                <tr>
                                    <th className="px-4 py-2 font-medium">Mes</th>
                                    <th className="px-2 py-2 font-medium">Tipo</th>
                                    <th className="px-4 py-2 font-medium">Concepto</th>
                                    <th className="px-4 py-2 font-medium">Categoría</th>
                                    <th className="px-4 py-2 font-medium">Subcategoría</th>
                                    <th className="px-4 py-2 text-right font-medium">Soles</th>
                                    <th className="px-4 py-2 text-right font-medium">Dólares</th>
                                    <th className="px-4 py-2 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {expenses.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-6 text-muted-foreground">
                                            {hasFilters ? 'No expenses match these filters.' : 'You have not recorded any expenses for this year.'}
                                        </td>
                                    </tr>
                                ) : (
                                    expenses.map((expense) => {
                                        const editing = editingId === expense.id;
                                        const filingCategory = categories.find((category) => String(category.id) === filing.data.category_id) ?? null;

                                        return (
                                            <tr key={expense.id} className="border-b border-border last:border-b-0">
                                                <td className="px-4 py-2">
                                                    {editing ? (
                                                        <RowSelect
                                                            id={`expense-${expense.id}-month`}
                                                            value={filing.data.month}
                                                            onValueChange={(value) => filing.setData('month', value)}
                                                            options={months.map((option) => ({
                                                                value: String(option.value),
                                                                label: option.label,
                                                            }))}
                                                            error={filing.errors.month}
                                                            className="w-full min-w-0"
                                                        />
                                                    ) : (
                                                        expense.month
                                                    )}
                                                </td>
                                                <td className="px-2 py-2 font-medium">
                                                    {editing ? (
                                                        <RowSelect
                                                            id={`expense-${expense.id}-type`}
                                                            value={filing.data.expense_type_id}
                                                            onValueChange={(value) => filing.setData('expense_type_id', value)}
                                                            options={expenseTypes.map((expenseType) => ({
                                                                value: String(expenseType.id),
                                                                label: expenseType.acronym,
                                                            }))}
                                                            error={filing.errors.expense_type_id}
                                                            className="w-full min-w-0"
                                                        />
                                                    ) : (
                                                        expense.type
                                                    )}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {editing ? (
                                                        <div className="grid w-full min-w-0 gap-1 overflow-hidden">
                                                            <Input id={`expense-${expense.id}-concept`} value={filing.data.concept} maxLength={255} onChange={(event) => filing.setData('concept', event.target.value)} className="h-8" />
                                                            <InputError message={filing.errors.concept} />
                                                        </div>
                                                    ) : (
                                                        expense.concept
                                                    )}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {editing ? (
                                                        <RowSelect
                                                            id={`expense-${expense.id}-category`}
                                                            value={filing.data.category_id}
                                                            onValueChange={changeFilingCategory}
                                                            options={categories.map((category) => ({
                                                                value: String(category.id),
                                                                label: category.name,
                                                            }))}
                                                            emptyLabel="Optional"
                                                            error={filing.errors.category_id}
                                                        />
                                                    ) : (
                                                        <CategoryPill label={expense.category} color={expense.categoryColor} />
                                                    )}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {editing ? (
                                                        <RowSelect
                                                            id={`expense-${expense.id}-subcategory`}
                                                            value={filing.data.subcategory_id}
                                                            onValueChange={(value) => filing.setData('subcategory_id', value)}
                                                            options={(filingCategory?.subcategories ?? []).map((subcategory) => ({
                                                                value: String(subcategory.id),
                                                                label: subcategory.name,
                                                            }))}
                                                            emptyLabel="Optional"
                                                            disabled={filing.data.category_id === ''}
                                                            error={filing.errors.subcategory_id}
                                                        />
                                                    ) : expense.subcategory === null || expense.subcategory === '' ? (
                                                        <span className="font-medium text-white">-</span>
                                                    ) : (
                                                        <span className="break-words">{expense.subcategory}</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2 text-right tabular-nums">
                                                    {editing ? (
                                                        <AmountEditor
                                                            id={`expense-${expense.id}-soles`}
                                                            amount={filing.data.currency === 'pen' ? filing.data.amount : ''}
                                                            error={filing.data.currency === 'pen' ? filing.errors.amount : undefined}
                                                            onAmount={(amount) =>
                                                                filing.setData((current) => ({
                                                                    ...current,
                                                                    currency: 'pen',
                                                                    amount,
                                                                }))
                                                            }
                                                        />
                                                    ) : (
                                                        <AmountPill amount={expense.soles} />
                                                    )}
                                                </td>
                                                <td className="px-4 py-2 text-right tabular-nums">
                                                    {editing ? (
                                                        <AmountEditor
                                                            id={`expense-${expense.id}-dollars`}
                                                            amount={filing.data.currency === 'usd' ? filing.data.amount : ''}
                                                            error={filing.data.currency === 'usd' ? filing.errors.amount : undefined}
                                                            onAmount={(amount) =>
                                                                filing.setData((current) => ({
                                                                    ...current,
                                                                    currency: 'usd',
                                                                    amount,
                                                                }))
                                                            }
                                                        />
                                                    ) : (
                                                        <AmountPill amount={expense.dollars} />
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right whitespace-nowrap">
                                                    {editing ? (
                                                        <div className="flex justify-end gap-2">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="icon"
                                                                className="shrink-0 border-transparent bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:ring-emerald-600/30"
                                                                disabled={filing.processing}
                                                                onClick={() => saveFiling(expense.id)}
                                                                data-test={`save-expense-${expense.id}`}
                                                            >
                                                                <Check />
                                                                <span className="sr-only">Save</span>
                                                            </Button>
                                                            <Button type="button" variant="destructive" size="icon" className="shrink-0" onClick={cancelFiling} data-test={`cancel-expense-${expense.id}`}>
                                                                <X />
                                                                <span className="sr-only">Cancel</span>
                                                            </Button>
                                                        </div>
                                                    ) : (
                                                        <div className="flex justify-end gap-2">
                                                            <Button type="button" variant="outline" size="icon" className="shrink-0 border-transparent bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:ring-emerald-600/30" onClick={() => openRowEditor(expense)} data-test={`edit-expense-${expense.id}`}>
                                                                <Pencil />
                                                                <span className="sr-only">Edit</span>
                                                            </Button>
                                                            <Dialog>
                                                                <DialogTrigger asChild>
                                                                    <Button type="button" variant="destructive" size="icon" className="shrink-0" data-test={`delete-expense-${expense.id}`}>
                                                                        <Trash2 />
                                                                        <span className="sr-only">Delete</span>
                                                                    </Button>
                                                                </DialogTrigger>
                                                                <DialogContent>
                                                                    <DialogTitle>Delete {expense.concept}?</DialogTitle>
                                                                    <DialogDescription>This removes the expense. You cannot undo this.</DialogDescription>
                                                                    <Form {...ExpenseController.destroy.form(expense.id)} disableWhileProcessing>
                                                                        {({ processing }) => (
                                                                            <DialogFooter className="gap-2">
                                                                                <DialogClose asChild>
                                                                                    <Button type="button" variant="secondary">
                                                                                        Cancel
                                                                                    </Button>
                                                                                </DialogClose>
                                                                                <Button type="submit" variant="destructive" disabled={processing} data-test={`confirm-delete-expense-${expense.id}`}>
                                                                                    Delete expense
                                                                                </Button>
                                                                            </DialogFooter>
                                                                        )}
                                                                    </Form>
                                                                </DialogContent>
                                                            </Dialog>
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3 text-sm">
                        <p className="text-muted-foreground">{pagination.total === 0 ? '0 expenses' : `${pagination.from}–${pagination.to} of ${pagination.total}`}</p>
                        <div className="flex flex-wrap items-center gap-2">
                            <Label htmlFor="expense-per-page" className="font-normal text-muted-foreground">
                                Rows per page
                            </Label>
                            <Select value={String(filters.perPage)} onValueChange={(value) => visit({ per_page: Number(value) })}>
                                <SelectTrigger id="expense-per-page" size="sm" className="w-20" data-test="expense-per-page">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {perPageOptions.map((option) => (
                                        <SelectItem key={option} value={String(option)}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button type="button" size="sm" variant="outline" disabled={pagination.currentPage <= 1} onClick={() => visit({ page: pagination.currentPage - 1 })}>
                                Previous
                            </Button>
                            <Button type="button" size="sm" variant="outline" disabled={pagination.currentPage >= pagination.lastPage} onClick={() => visit({ page: pagination.currentPage + 1 })}>
                                Next
                            </Button>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}

function MonthChip({ active, onClick, children }: { active: boolean; onClick: () => void; children: ReactNode }) {
    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            className={cn(
                'rounded-full border px-2.5 py-1 text-xs transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                active ? 'border-primary bg-primary font-medium text-primary-foreground' : 'border-border text-muted-foreground hover:bg-accent hover:text-accent-foreground',
            )}
        >
            {children}
        </button>
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
