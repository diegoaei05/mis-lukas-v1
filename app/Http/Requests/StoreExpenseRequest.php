<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Enums\Month;
use App\Models\ExpenseType;
use App\Models\Subcategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExpenseRequest extends FormRequest
{
    private ?ExpenseType $resolvedExpenseType = null;

    private ?Subcategory $resolvedSubcategory = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->errorBag = 'createExpense';

        $fields = [];

        $concept = $this->input('concept');

        if (is_string($concept)) {
            $fields['concept'] = trim($concept);
        }

        $amount = $this->input('amount');

        if (is_string($amount) && str_contains($amount, ',') && ! str_contains($amount, '.')) {
            $fields['amount'] = str_replace(',', '.', trim($amount));
        }

        if ($fields !== []) {
            $this->merge($fields);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', Rule::enum(Month::class)],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'expense_type_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'subcategory_id' => ['required', 'integer'],
            'concept' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
        ];
    }

    /**
     * The signed-in user's expense type chosen for this expense.
     */
    public function expenseType(): ExpenseType
    {
        if ($this->resolvedExpenseType === null) {
            abort(404);
        }

        return $this->resolvedExpenseType;
    }

    /**
     * The signed-in user's subcategory chosen for this expense.
     */
    public function subcategory(): Subcategory
    {
        if ($this->resolvedSubcategory === null) {
            abort(404);
        }

        return $this->resolvedSubcategory;
    }

    /**
     * Reject a type, category, or subcategory the user does not own.
     *
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $user = $this->user();

                $expenseType = $user?->expenseTypes()->find($this->integer('expense_type_id'));
                $category = $user?->categories()->find($this->integer('category_id'));
                $subcategory = Subcategory::query()
                    ->whereKey($this->integer('subcategory_id'))
                    ->whereHas('category', fn ($query) => $query->where('user_id', $user?->id))
                    ->first();

                if (! $expenseType instanceof ExpenseType || $category === null || $subcategory === null) {
                    abort(404);
                }

                if ($subcategory->category_id !== $category->id) {
                    $validator->errors()->add(
                        'subcategory_id',
                        'The subcategory does not belong to the selected category.',
                    );

                    return;
                }

                $this->resolvedExpenseType = $expenseType;
                $this->resolvedSubcategory = $subcategory;
            },
        ];
    }
}
