<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Subcategory;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSubcategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        $subcategory = $this->route('subcategory');

        if (! $subcategory instanceof Subcategory) {
            return Response::denyAsNotFound();
        }

        return Gate::inspect('update', $subcategory);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $subcategory = $this->route('subcategory');

        $this->errorBag = 'updateSubcategory-'.($subcategory instanceof Subcategory ? $subcategory->id : 'unknown');

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
        $subcategory = $this->route('subcategory');
        $categoryId = $category instanceof Category ? $category->id : null;
        $subcategoryId = $subcategory instanceof Subcategory ? $subcategory->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($categoryId, $subcategoryId): void {
                    if (! is_string($value) || $value === '' || $categoryId === null || $subcategoryId === null) {
                        return;
                    }

                    $taken = Subcategory::query()
                        ->where('category_id', $categoryId)
                        ->whereKeyNot($subcategoryId)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->exists();

                    if ($taken) {
                        $fail('This category already has a subcategory with this name.');
                    }
                },
            ],
        ];
    }
}
