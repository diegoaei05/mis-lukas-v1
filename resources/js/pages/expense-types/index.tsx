import { Form, Head } from '@inertiajs/react';
import ExpenseTypeController from '@/actions/App/Http/Controllers/ExpenseTypeController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/expense-types';

type ExpenseTypeItem = {
    id: number;
    acronym: string;
    description: string;
};

export default function ExpenseTypesIndex({ expenseTypes }: { expenseTypes: ExpenseTypeItem[] }) {
    return (
        <>
            <Head title="Expense Types" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">Expense Types</h2>
                    <p className="text-sm text-muted-foreground">Create the acronym and description for each way you pay.</p>
                </div>

                <Form {...ExpenseTypeController.store.form()} errorBag="createExpenseType" resetOnSuccess disableWhileProcessing className="flex max-w-3xl flex-col gap-4 sm:flex-row sm:items-start">
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2 sm:w-40">
                                <Label htmlFor="expense-type-acronym">Acronym</Label>
                                <Input id="expense-type-acronym" name="acronym" required maxLength={20} placeholder="IO" data-test="expense-type-acronym" />
                                <InputError message={errors.acronym} />
                            </div>
                            <div className="grid min-w-0 flex-1 gap-2">
                                <Label htmlFor="expense-type-description">Description</Label>
                                <Input id="expense-type-description" name="description" required maxLength={255} placeholder="IO credit card" data-test="expense-type-description" />
                                <InputError message={errors.description} />
                            </div>
                            <Button type="submit" disabled={processing} className="sm:mt-6" data-test="create-expense-type-button">
                                Add type
                            </Button>
                        </>
                    )}
                </Form>

                <div className="max-w-3xl overflow-hidden rounded-xl border border-border bg-card">
                    <table className="w-full text-sm">
                        <caption className="sr-only">Your expense types</caption>
                        <thead className="border-b border-border bg-muted/40 text-left">
                            <tr>
                                <th className="w-32 px-4 py-3 font-medium">Acronym</th>
                                <th className="px-4 py-3 font-medium">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            {expenseTypes.length === 0 ? (
                                <tr>
                                    <td colSpan={2} className="px-4 py-6 text-muted-foreground">
                                        You have not created any expense types yet.
                                    </td>
                                </tr>
                            ) : (
                                expenseTypes.map((expenseType) => (
                                    <tr key={expenseType.id} className="border-b border-border last:border-b-0">
                                        <td className="px-4 py-3 font-medium">{expenseType.acronym}</td>
                                        <td className="px-4 py-3">{expenseType.description}</td>
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

ExpenseTypesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Expense Types',
            href: index(),
        },
    ],
};
