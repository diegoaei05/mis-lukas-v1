<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use App\Models\ExpenseType;
use App\Models\FixedExpense;
use App\Models\Subcategory;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFixedExpenseRequest extends FormRequest
{
    private ?ExpenseType $resolvedExpenseType = null;

    private ?Subcategory $resolvedSubcategory = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        $fixedExpense = $this->route('fixedExpense');

        if (! $fixedExpense instanceof FixedExpense) {
            return Response::denyAsNotFound();
        }

        return Gate::inspect('update', $fixedExpense);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $fixedExpense = $this->route('fixedExpense');

        $this->errorBag = 'updateFixedExpense-'.($fixedExpense instanceof FixedExpense ? $fixedExpense->id : 'unknown');

        $fields = [];

        $name = $this->input('name');

        if (is_string($name)) {
            $fields['name'] = trim($name);
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
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $fixedExpense = $this->route('fixedExpense');

                    $taken = FixedExpense::query()
                        ->where('user_id', $this->user()->id)
                        ->when(
                            $fixedExpense instanceof FixedExpense,
                            fn ($query) => $query->whereKeyNot($fixedExpense->id),
                        )
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->exists();

                    if ($taken) {
                        $fail('You already have a fixed expense with this name.');
                    }
                },
            ],
            'expense_type_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'subcategory_id' => ['required', 'integer'],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'view_month' => ['nullable', 'integer'],
            'view_year' => ['nullable', 'integer'],
        ];
    }

    /**
     * The signed-in user's expense type chosen for this bill.
     */
    public function expenseType(): ExpenseType
    {
        if ($this->resolvedExpenseType === null) {
            abort(404);
        }

        return $this->resolvedExpenseType;
    }

    /**
     * The signed-in user's subcategory chosen for this bill.
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
