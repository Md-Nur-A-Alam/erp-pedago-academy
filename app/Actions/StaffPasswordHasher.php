<?php

namespace App\Actions;

use Illuminate\Contracts\Hashing\Hasher;

/**
 * Stores passwords with the reversible staff cipher while still accepting older bcrypt hashes.
 * An account that still has a bcrypt hash is converted the next time its owner signs in.
 */
class StaffPasswordHasher implements Hasher
{
    public const PREFIX = 'pdg1$';

    public function __construct(private StaffPasswordCipher $cipher) {}

    /**
     * @return array{algo: string|int|null, algoName: string, options: array<string, mixed>}
     */
    public function info($hashedValue): array
    {
        if ($this->isReadable((string) $hashedValue)) {
            return ['algo' => 'pedago', 'algoName' => 'pedago', 'options' => []];
        }

        return password_get_info((string) $hashedValue);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function make($value, array $options = []): string
    {
        return self::PREFIX.$this->cipher->encrypt((string) $value);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function check($value, $hashedValue, array $options = []): bool
    {
        if ($hashedValue === null || $hashedValue === '') {
            return false;
        }

        return $this->isReadable($hashedValue)
            ? hash_equals($hashedValue, $this->make($value))
            : password_verify((string) $value, $hashedValue);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function needsRehash($hashedValue, array $options = []): bool
    {
        return ! $this->isReadable((string) $hashedValue);
    }

    public function verifyConfiguration(string $value): bool
    {
        return true;
    }

    public function isReadable(string $storedValue): bool
    {
        return str_starts_with($storedValue, self::PREFIX);
    }

    /**
     * The original password, or null when the account still holds a one-way hash.
     */
    public function reveal(string $storedValue): ?string
    {
        return $this->isReadable($storedValue)
            ? $this->cipher->decrypt(substr($storedValue, strlen(self::PREFIX)))
            : null;
    }
}
