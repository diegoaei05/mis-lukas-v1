<?php

namespace App\Http\Requests;

use App\Models\ExpenseType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseTypeRequest extends FormRequest
{
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
        $this->errorBag = 'createExpenseType';

        $fields = [];

        foreach (['acronym', 'description'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $fields[$field] = trim($value);
            }
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
            'acronym' => [
                'required',
                'string',
                'max:20',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $taken = ExpenseType::query()
                        ->where('user_id', $this->user()->id)
                        ->whereRaw('LOWER(acronym) = ?', [mb_strtolower($value)])
                        ->exists();

                    if ($taken) {
                        $fail('You already have an expense type with this acronym.');
                    }
                },
            ],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
