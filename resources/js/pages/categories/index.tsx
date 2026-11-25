import { Form, Head } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import SubcategoryController from '@/actions/App/Http/Controllers/SubcategoryController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { categoryFillClass } from '@/lib/category-colors';
import { cn } from '@/lib/utils';
import { index } from '@/routes/categories';

type SubcategoryItem = {
    id: number;
    name: string;
};

type CategoryItem = {
    id: number;
    name: string;
    color: string | null;
    subcategories: SubcategoryItem[];
};

type ColorOption = {
    value: string;
    label: string;
};

function ColorSelect({ id, colors, value, onValueChange, testPrefix, error }: { id: string; colors: ColorOption[]; value: string; onValueChange: (value: string) => void; testPrefix: string; error?: string }) {
    return (
        <div className="grid w-full gap-2 sm:w-44">
            <Label htmlFor={id}>Color</Label>
            <Select value={value === '' ? undefined : value} onValueChange={onValueChange}>
                <SelectTrigger id={id} className="w-full" data-test={`${testPrefix}-color`}>
                    <SelectValue placeholder="Color" />
                </SelectTrigger>
                <SelectContent>
                    {colors.map((color) => (
                        <SelectItem key={color.value} value={color.value}>
                            <span className="flex items-center gap-2">
                                <span className={cn('size-3 shrink-0 rounded-full', categoryFillClass(color.value))} />
                                {color.label}
                            </span>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <input type="hidden" name="color" value={value} required data-test={`${testPrefix}-color-value`} />
            <InputError message={error} />
        </div>
    );
}

function NewCategoryForm({ colors }: { colors: ColorOption[] }) {
    const [color, setColor] = useState('');

    return (
        <Form {...CategoryController.store.form()} errorBag="createCategory" resetOnSuccess disableWhileProcessing className="flex max-w-3xl flex-col gap-2 sm:flex-row sm:items-start">
            {({ errors, processing, wasSuccessful }) => <NewCategoryFields colors={colors} color={color} onColorChange={setColor} errors={errors} processing={processing} wasSuccessful={wasSuccessful} />}
        </Form>
    );
}

function NewCategoryFields({ colors, color, onColorChange, errors, processing, wasSuccessful }: { colors: ColorOption[]; color: string; onColorChange: (value: string) => void; errors: Record<string, string>; processing: boolean; wasSuccessful: boolean }) {
    useEffect(() => {
        if (wasSuccessful) {
            onColorChange('');
        }
    }, [wasSuccessful, onColorChange]);

    return (
        <>
            <div className="grid min-w-0 flex-1 gap-2">
                <Label htmlFor="category-name">New category</Label>
                <Input id="category-name" name="name" required placeholder="New category" data-test="category-name" />
                <InputError message={errors.name} />
            </div>
            <ColorSelect id="category-color" colors={colors} value={color} onValueChange={onColorChange} testPrefix="category" error={errors.color} />
            <Button type="submit" disabled={processing} className="sm:mt-6" data-test="create-category-button">
                Add category
            </Button>
        </>
    );
}

function SubcategoryNameForm({ subcategoryId, categoryId, name, onSaved }: { subcategoryId: number; categoryId: number; name: string; onSaved: () => void }) {
    return (
        <Form {...SubcategoryController.update.form({ category: categoryId, subcategory: subcategoryId })} errorBag={`updateSubcategory-${subcategoryId}`} disableWhileProcessing className="flex items-start gap-2">
            {({ errors, processing, wasSuccessful }) => <SubcategoryNameFields subcategoryId={subcategoryId} name={name} errors={errors} processing={processing} wasSuccessful={wasSuccessful} onSaved={onSaved} />}
        </Form>
    );
}

function SubcategoryNameFields({ subcategoryId, name, errors, processing, wasSuccessful, onSaved }: { subcategoryId: number; name: string; errors: Record<string, string>; processing: boolean; wasSuccessful: boolean; onSaved: () => void }) {
    useEffect(() => {
        if (wasSuccessful) {
            onSaved();
        }
    }, [wasSuccessful, onSaved]);

    return (
        <>
            <div className="grid min-w-0 flex-1 gap-2">
                <Label htmlFor={`update-subcategory-name-${subcategoryId}`} className="sr-only">
                    Subcategory name
                </Label>
                <Input id={`update-subcategory-name-${subcategoryId}`} name="name" required defaultValue={name} data-test={`update-subcategory-name-${subcategoryId}`} />
                <InputError message={errors.name} />
            </div>
            <Button type="submit" disabled={processing} data-test={`update-subcategory-button-${subcategoryId}`}>
                Save
            </Button>
        </>
    );
}

function UpdateCategoryForm({ category, colors, onSaved }: { category: CategoryItem; colors: ColorOption[]; onSaved: () => void }) {
    const [color, setColor] = useState(category.color ?? '');

    return (
        <Form {...CategoryController.update.form(category.id)} errorBag={`updateCategory-${category.id}`} disableWhileProcessing className="flex flex-col gap-2 border-b border-border p-4 sm:flex-row sm:items-start">
            {({ errors, processing, wasSuccessful }) => <UpdateCategoryFields category={category} colors={colors} color={color} onColorChange={setColor} errors={errors} processing={processing} wasSuccessful={wasSuccessful} onSaved={onSaved} />}
        </Form>
    );
}

function UpdateCategoryFields({ category, colors, color, onColorChange, errors, processing, wasSuccessful, onSaved }: { category: CategoryItem; colors: ColorOption[]; color: string; onColorChange: (value: string) => void; errors: Record<string, string>; processing: boolean; wasSuccessful: boolean; onSaved: () => void }) {
    useEffect(() => {
        if (wasSuccessful) {
            onSaved();
        }
    }, [wasSuccessful, onSaved]);

    return (
        <>
            <div className="grid min-w-0 flex-1 gap-2">
                <Label htmlFor={`update-category-name-${category.id}`}>Category name</Label>
                <Input id={`update-category-name-${category.id}`} name="name" required defaultValue={category.name} data-test={`update-category-name-${category.id}`} />
                <InputError message={errors.name} />
            </div>
            <ColorSelect id={`update-category-color-${category.id}`} colors={colors} value={color} onValueChange={onColorChange} testPrefix={`update-category-${category.id}`} error={errors.color} />
            <Button type="submit" disabled={processing} className="sm:mt-6" data-test={`update-category-button-${category.id}`}>
                Save category
            </Button>
        </>
    );
}

export default function CategoriesIndex({ categories, selectedCategoryId, colors }: { categories: CategoryItem[]; selectedCategoryId: number | null; colors: ColorOption[] }) {
    const [selectedId, setSelectedId] = useState<number | null>(selectedCategoryId ?? categories[0]?.id ?? null);
    const [editingCategoryId, setEditingCategoryId] = useState<number | null>(null);
    const [editingSubcategoryId, setEditingSubcategoryId] = useState<number | null>(null);
    const closeEditor = useCallback(() => {
        setEditingCategoryId(null);
    }, []);
    const closeSubcategoryEditor = useCallback(() => {
        setEditingSubcategoryId(null);
    }, []);

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
                    <p className="text-sm text-muted-foreground">Add a category, or open one in the card to change its name, color, or subcategories.</p>
                </div>

                <NewCategoryForm colors={colors} />

                {selectedCategory ? (
                    <div className="max-w-3xl overflow-hidden rounded-xl border border-border bg-card">
                        <div className="flex items-end gap-2 border-b border-border p-4">
                            <div className="grid min-w-0 flex-1 gap-2 sm:max-w-64">
                                <Label htmlFor="category">Category</Label>
                                <Select value={String(selectedCategory.id)} onValueChange={(value) => { setSelectedId(Number(value)); setEditingSubcategoryId(null); }}>
                                    <SelectTrigger id="category" className="w-full">
                                        <SelectValue placeholder="Choose a category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem key={category.id} value={String(category.id)}>
                                                <span className="flex items-center gap-2">
                                                    {categoryFillClass(category.color) !== '' && <span className={cn('size-3 shrink-0 rounded-full', categoryFillClass(category.color))} />}
                                                    {category.name}
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button type="button" variant="outline" size="icon" className="shrink-0 border-transparent bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:ring-emerald-600/30" aria-pressed={editingCategoryId === selectedCategory.id} aria-label={`Edit ${selectedCategory.name}`} onClick={() => setEditingCategoryId((current) => (current === selectedCategory.id ? null : selectedCategory.id))} data-test={`edit-category-${selectedCategory.id}`}>
                                <Pencil />
                            </Button>
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button type="button" variant="destructive" size="icon" className="shrink-0" aria-label={`Delete ${selectedCategory.name}`} data-test={`delete-category-${selectedCategory.id}`}>
                                        <Trash2 />
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>Delete {selectedCategory.name}?</DialogTitle>
                                    <DialogDescription>This removes {selectedCategory.name} and its subcategories. You cannot undo this.</DialogDescription>
                                    <Form {...CategoryController.destroy.form(selectedCategory.id)} disableWhileProcessing>
                                        {({ processing }) => (
                                            <DialogFooter className="gap-2">
                                                <DialogClose asChild>
                                                    <Button type="button" variant="secondary">
                                                        Cancel
                                                    </Button>
                                                </DialogClose>
                                                <Button type="submit" variant="destructive" disabled={processing} data-test={`confirm-delete-category-${selectedCategory.id}`}>
                                                    Delete category
                                                </Button>
                                            </DialogFooter>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        </div>
                        {editingCategoryId === selectedCategory.id && <UpdateCategoryForm key={selectedCategory.id} category={selectedCategory} colors={colors} onSaved={closeEditor} />}
                        <table className="w-full text-sm">
                            <caption className="sr-only">Subcategories in {selectedCategory.name}</caption>
                            <thead className="border-b border-border bg-muted/40 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Subcategory</th>
                                    <th className="w-24 px-4 py-3">
                                        <span className="sr-only">Actions</span>
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
                                            <td className="px-4 py-2">
                                                {editingSubcategoryId === subcategory.id ? (
                                                    <SubcategoryNameForm subcategoryId={subcategory.id} categoryId={selectedCategory.id} name={subcategory.name} onSaved={closeSubcategoryEditor} />
                                                ) : (
                                                    subcategory.name
                                                )}
                                            </td>
                                            <td className="px-2 py-1 text-right whitespace-nowrap">
                                                <div className="flex justify-end gap-1.5">
                                                    <Button type="button" variant="outline" size="icon-sm" className="shrink-0 border-transparent bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:ring-emerald-600/30" aria-pressed={editingSubcategoryId === subcategory.id} aria-label={`Edit ${subcategory.name}`} onClick={() => setEditingSubcategoryId((current) => (current === subcategory.id ? null : subcategory.id))} data-test={`edit-subcategory-${subcategory.id}`}>
                                                        <Pencil className="size-3.5" />
                                                    </Button>
                                                    <Dialog>
                                                    <DialogTrigger asChild>
                                                        <Button type="button" variant="destructive" size="icon-sm" className="shrink-0" aria-label={`Delete ${subcategory.name}`} data-test={`delete-subcategory-${subcategory.id}`}>
                                                            <Trash2 className="size-3.5" />
                                                            <span className="sr-only">Delete</span>
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
                                                </div>
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
