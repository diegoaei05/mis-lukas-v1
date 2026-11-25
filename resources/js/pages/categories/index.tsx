import { Form, Head } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import SubcategoryController from '@/actions/App/Http/Controllers/SubcategoryController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { index } from '@/routes/categories';

type SubcategoryItem = {
    id: number;
    name: string;
};

type CategoryItem = {
    id: number;
    name: string;
    subcategories: SubcategoryItem[];
};

export default function CategoriesIndex({ categories, selectedCategoryId }: { categories: CategoryItem[]; selectedCategoryId: number | null }) {
    const [selectedId, setSelectedId] = useState<number | null>(selectedCategoryId ?? categories[0]?.id ?? null);

    useEffect(() => {
        if (selectedCategoryId !== null && categories.some((category) => category.id === selectedCategoryId)) {
            setSelectedId(selectedCategoryId);

            return;
        }

        setSelectedId((current) => {
            if (current !== null && categories.some((category) => category.id === current)) {
                return current;
            }

            return categories[0]?.id ?? null;
        });
    }, [categories, selectedCategoryId]);

    const selectedCategory = categories.find((category) => category.id === selectedId) ?? null;

    return (
        <>
            <Head title="Categories" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-0.5">
                    <h2 className="text-xl font-semibold tracking-tight">Categories</h2>
                    <p className="text-sm text-muted-foreground">Choose a category, then change the subcategories that belong to it.</p>
                </div>

                <div className="flex max-w-3xl flex-col gap-4 sm:flex-row sm:items-start">
                    {categories.length > 0 && (
                        <div className="grid gap-2">
                            <Label htmlFor="category">Category</Label>
                            <Select value={selectedCategory ? String(selectedCategory.id) : undefined} onValueChange={(value) => setSelectedId(Number(value))}>
                                <SelectTrigger id="category" className="w-full sm:w-64">
                                    <SelectValue placeholder="Choose a category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((category) => (
                                        <SelectItem key={category.id} value={String(category.id)}>
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <Form {...CategoryController.store.form()} errorBag="createCategory" resetOnSuccess disableWhileProcessing className="grid min-w-0 flex-1 gap-2">
                        {({ errors, processing }) => (
                            <>
                                <Label htmlFor="category-name">New category</Label>
                                <div className="flex items-start gap-2">
                                    <div className="grid min-w-0 flex-1 gap-2">
                                        <Input id="category-name" name="name" required placeholder="New category" data-test="category-name" />
                                        <InputError message={errors.name} />
                                    </div>
                                    <Button type="submit" disabled={processing} data-test="create-category-button">
                                        Add category
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                {selectedCategory ? (
                    <div className="max-w-3xl overflow-hidden rounded-xl border border-border bg-card">
                        <table className="w-full text-sm">
                            <caption className="sr-only">Subcategories in {selectedCategory.name}</caption>
                            <thead className="border-b border-border bg-muted/40 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Subcategory</th>
                                    <th className="w-16 px-4 py-3">
                                        <span className="sr-only">Delete</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {selectedCategory.subcategories.length === 0 ? (
                                    <tr>
                                        <td colSpan={2} className="px-4 py-6 text-muted-foreground">
                                            No subcategories yet.
                                        </td>
                                    </tr>
                                ) : (
                                    selectedCategory.subcategories.map((subcategory) => (
                                        <tr key={subcategory.id} className="border-b border-border/70 last:border-b-0">
                                            <td className="px-4 py-2">{subcategory.name}</td>
                                            <td className="px-2 py-1 text-right">
                                                <Dialog>
                                                    <DialogTrigger asChild>
                                                        <Button type="button" variant="ghost" size="icon" aria-label={`Delete ${subcategory.name}`} data-test={`delete-subcategory-${subcategory.id}`}>
                                                            <X />
                                                        </Button>
                                                    </DialogTrigger>
                                                    <DialogContent>
                                                        <DialogTitle>Delete {subcategory.name}?</DialogTitle>
                                                        <DialogDescription>
                                                            This removes {subcategory.name} from {selectedCategory.name}. You cannot undo this.
                                                        </DialogDescription>
                                                        <Form
                                                            {...SubcategoryController.destroy.form({
                                                                category: selectedCategory.id,
                                                                subcategory: subcategory.id,
                                                            })}
                                                            disableWhileProcessing
                                                        >
                                                            {({ processing }) => (
                                                                <DialogFooter className="gap-2">
                                                                    <DialogClose asChild>
                                                                        <Button type="button" variant="secondary">
                                                                            Cancel
                                                                        </Button>
                                                                    </DialogClose>
                                                                    <Button type="submit" variant="destructive" disabled={processing} data-test={`confirm-delete-subcategory-${subcategory.id}`}>
                                                                        Delete subcategory
                                                                    </Button>
                                                                </DialogFooter>
                                                            )}
                                                        </Form>
                                                    </DialogContent>
                                                </Dialog>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>

                        <Form {...SubcategoryController.store.form(selectedCategory.id)} errorBag={`subcategory-${selectedCategory.id}`} resetOnSuccess disableWhileProcessing className="flex items-start gap-2 border-t border-border bg-muted/30 p-3">
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid min-w-0 flex-1 gap-2">
                                        <Label htmlFor={`subcategory-name-${selectedCategory.id}`} className="sr-only">
                                            Add a subcategory to {selectedCategory.name}
                                        </Label>
                                        <Input id={`subcategory-name-${selectedCategory.id}`} name="name" required placeholder={`Add a subcategory to ${selectedCategory.name}`} data-test={`subcategory-name-${selectedCategory.id}`} />
                                        <InputError message={errors.name} />
                                    </div>
                                    <Button type="submit" variant="outline" disabled={processing} data-test={`create-subcategory-button-${selectedCategory.id}`}>
                                        Add
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">You have not created any categories yet.</p>
                )}
            </div>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Categories',
            href: index(),
        },
    ],
};
