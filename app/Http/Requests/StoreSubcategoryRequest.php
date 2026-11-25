<?php

namespace App\Http\Requests;

use App\Enums\CategoryColor;
use App\Models\Category;
use App\Models\Subcategory;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSubcategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        $category = $this->route('category');

        if (! $category instanceof Category) {
            return Response::denyAsNotFound();
        }

        return Gate::inspect('create', [Subcategory::class, $category]);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $category = $this->route('category');

        if ($category instanceof Category) {
            $this->errorBag = 'subcategory-'.$category->id;
        }

        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge([
                'name' => trim($name),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category instanceof Category ? $category->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($categoryId): void {
                    if (! is_string($value) || $value === '' || $categoryId === null) {
                        return;
                    }

                    $taken = Subcategory::query()
                        ->where('category_id', $categoryId)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->exists();

                    if ($taken) {
                        $fail('This category already has a subcategory with this name.');
                    }
                },
            ],
            'color' => ['required', Rule::enum(CategoryColor::class)],
        ];
    }
}
