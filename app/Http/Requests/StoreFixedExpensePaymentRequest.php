<?php

namespace App\Http\Requests;

use App\Enums\Month;
use App\Models\FixedExpense;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFixedExpensePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        $fixedExpense = $this->route('fixedExpense');

        if (! $fixedExpense instanceof FixedExpense) {
            return Response::denyAsNotFound();
        }

        return Gate::inspect('pay', $fixedExpense);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $fixedExpense = $this->route('fixedExpense');

        $this->errorBag = 'payment-'.($fixedExpense instanceof FixedExpense ? $fixedExpense->id : 'unknown');

        $amount = $this->input('amount');

        if (is_string($amount) && str_contains($amount, ',') && ! str_contains($amount, '.')) {
            $this->merge([
                'amount' => str_replace(',', '.', trim($amount)),
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
        return [
            'month' => ['required', Rule::enum(Month::class)],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
        ];
    }

    /**
     * Reject a month that is already paid.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $fixedExpense = $this->route('fixedExpense');

                if (! $fixedExpense instanceof FixedExpense) {
                    abort(404);
                }

                $alreadyPaid = $fixedExpense->payments()
                    ->where('year', $this->integer('year'))
                    ->where('month', $this->integer('month'))
                    ->exists();

                if ($alreadyPaid) {
                    $validator->errors()->add('amount', 'This bill is already marked paid for this month.');
                }
            },
        ];
    }
}
