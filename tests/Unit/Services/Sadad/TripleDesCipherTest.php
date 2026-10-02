<?php

use App\Services\Sadad\TripleDesCipher;

test('encrypts the payment sign string with triple des', function () {
    $cipher = new TripleDesCipher('MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0');

    expect($cipher->encrypt('24095674;1234567890123456;150000'))
        ->toBe(trim(file_get_contents(dirname(__DIR__, 3).'/Fixtures/sadad-payment-sign.txt')));
});

test('encrypts the verify token with triple des', function () {
    $cipher = new TripleDesCipher('MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0');

    expect($cipher->encrypt('gateway-token'))
        ->toBe(trim(file_get_contents(dirname(__DIR__, 3).'/Fixtures/sadad-verify-sign.txt')));
});
