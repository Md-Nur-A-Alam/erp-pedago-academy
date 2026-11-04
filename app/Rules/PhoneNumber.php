<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^(?:\+[1-9][0-9]{6,14}|[0-9]{7,15})$/D', $value) !== 1) {
            $fail('The :attribute must contain 7 to 15 digits. Use + and a country code for international numbers.');
        }
    }
}
