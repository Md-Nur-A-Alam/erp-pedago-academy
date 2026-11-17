<?php

namespace App\Actions;

use InvalidArgumentException;

/**
 * The company's reversible password transformation ("Pass encrypt and decrypt formula").
 *
 * Encrypt: reverse the bytes, chain each byte with the one before it and its position, then add the
 * repeating key. The result is stored as lowercase hexadecimal.
 */
class StaffPasswordCipher
{
    public function __construct(private string $key)
    {
        if ($key === '') {
            throw new InvalidArgumentException('The staff password key is not configured.');
        }
    }

    public function encrypt(string $password): string
    {
        $reversed = array_values(unpack('C*', strrev($password)) ?: []);
        $key = array_values(unpack('C*', $this->key));
        $cipher = '';

        foreach ($reversed as $index => $byte) {
            $chained = $index === 0 ? $byte : ($byte + $reversed[$index - 1] + $index) % 256;
            $cipher .= sprintf('%02x', ($chained + $key[$index % count($key)]) % 256);
        }

        return $cipher;
    }

    public function decrypt(string $cipher): string
    {
        if ($cipher === '' || strlen($cipher) % 2 !== 0 || ! ctype_xdigit($cipher)) {
            throw new InvalidArgumentException('The stored password is not in the expected format.');
        }

        $bytes = array_values(unpack('C*', hex2bin($cipher)));
        $key = array_values(unpack('C*', $this->key));
        $reversed = [];

        foreach ($bytes as $index => $byte) {
            $chained = ($byte - $key[$index % count($key)] + 256) % 256;
            $reversed[$index] = $index === 0 ? $chained : (($chained - $reversed[$index - 1] - $index) % 256 + 256) % 256;
        }

        return strrev(pack('C*', ...$reversed));
    }
}
