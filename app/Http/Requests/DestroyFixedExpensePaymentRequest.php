<?php

namespace App\Http\Requests;

use App\Enums\Month;
use App\Models\FixedExpense;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DestroyFixedExpensePaymentRequest extends FormRequest
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
        ];
    }
}
