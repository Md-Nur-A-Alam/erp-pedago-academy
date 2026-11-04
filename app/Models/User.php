<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['phone_number', 'password', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public static function normalizePhoneNumber(string $phoneNumber): string
    {
        $normalizedPhoneNumber = Str::of($phoneNumber)
            ->replaceMatches('/[\s().-]+/', '')
            ->toString();

        return str_starts_with($normalizedPhoneNumber, '00')
            ? '+'.substr($normalizedPhoneNumber, 2)
            : $normalizedPhoneNumber;
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }
}
