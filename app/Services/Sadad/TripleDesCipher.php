<?php

namespace App\Services\Sadad;

use RuntimeException;

class TripleDesCipher
{
    public function __construct(private string $encodedKey) {}

    /**
     * Encrypt with Triple DES (ECB, PKCS7) and return Base64.
     *
     * Sadad terminal keys are Base64. A 16-byte key is expanded to 24 bytes
     * by repeating the first 8 bytes, which is two-key Triple DES.
     */
    public function encrypt(string $plainText): string
    {
        $key = base64_decode($this->encodedKey, true);

        if ($key === false || ! in_array(strlen($key), [16, 24], true)) {
            throw new RuntimeException('کلید تراکنش سداد نامعتبر است.');
        }

        if (strlen($key) === 16) {
            $key .= substr($key, 0, 8);
        }

        $cipherText = openssl_encrypt($plainText, 'DES-EDE3', $key, OPENSSL_RAW_DATA);

        if ($cipherText === false) {
            throw new RuntimeException('رمزنگاری اطلاعات تراکنش ناموفق بود.');
        }

        return base64_encode($cipherText);
    }
}
