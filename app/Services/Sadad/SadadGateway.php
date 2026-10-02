<?php

namespace App\Services\Sadad;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SadadGateway
{
    public function __construct(private TripleDesCipher $cipher) {}

    /**
     * @return array<string, mixed>
     */
    public function requestPayment(int $amount, string $orderId, string $returnUrl): array
    {
        $terminalId = (string) config('services.sadad.terminal_id');

        $response = $this->client()->post((string) config('services.sadad.payment_request_url'), [
            'MerchantId' => (string) config('services.sadad.merchant_id'),
            'TerminalId' => $terminalId,
            'Amount' => $amount,
            'OrderId' => (int) $orderId,
            'LocalDateTime' => now()->timezone('Asia/Tehran')->format('m/d/Y g:i:s a'),
            'ReturnUrl' => $returnUrl,
            'SignData' => $this->cipher->encrypt($terminalId.';'.$orderId.';'.$amount),
            'MultiplexingData' => $this->multiplexingData($amount),
        ]);

        return $this->decode($response);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function verify(string $token): array
    {
        $response = $this->client()
            ->retry(2, 0, function (Throwable $exception): bool {
                return $exception instanceof ConnectionException;
            })
            ->post((string) config('services.sadad.verify_url'), [
                'Token' => $token,
                'SignData' => $this->cipher->encrypt($token),
            ]);

        return $this->decode($response);
    }

    public function purchaseUrl(string $token): string
    {
        return (string) config('services.sadad.purchase_url').'?'.http_build_query([
            'Token' => $token,
        ]);
    }

    public function paymentRequestSucceeded(array $response): bool
    {
        return in_array($response['ResCode'] ?? null, [0, '0'], true)
            && is_string($response['Token'] ?? null)
            && $response['Token'] !== '';
    }

    public function callbackSucceeded(mixed $resCode): bool
    {
        return in_array($resCode, [0, '0'], true);
    }

    /**
     * Verify ResCode 0 is a new success. ResCode 100 means the transaction
     * was already verified and is still a final success.
     */
    public function verifySucceeded(mixed $resCode): bool
    {
        return in_array($resCode, [0, '0', 100, '100'], true);
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(30);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $decoded = $response->json();

        if (is_array($decoded)) {
            return $decoded;
        }

        $body = trim($response->body());

        return $body === '' ? [] : ['Description' => $body];
    }

    /**
     * Multiplexing terminals must send MultiplexingData. When a single amount
     * row omits Value, the whole payment amount is assigned to that account.
     *
     * @return array{Type: string, MultiplexingRows: list<array{IbanNumber: int|string, Value: int}>}
     */
    private function multiplexingData(int $amount): array
    {
        $type = (string) config('services.sadad.multiplexing.type');
        $rows = config('services.sadad.multiplexing.rows');

        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('اطلاعات تسهیم درگاه تنظیم نشده است.');
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! array_key_exists('IbanNumber', $row)) {
                throw new RuntimeException('ردیف تسهیم نامعتبر است.');
            }

            $value = $row['Value'] ?? null;

            if ($value === null && $type === 'Amount' && count($rows) === 1) {
                $value = $amount;
            }

            if ($value === null) {
                throw new RuntimeException('مبلغ یا درصد ردیف تسهیم مشخص نشده است.');
            }

            $ibanNumber = $row['IbanNumber'];
            $iban = is_string($ibanNumber) ? $ibanNumber : (string) $ibanNumber;

            $normalized[] = [
                'IbanNumber' => str_starts_with($iban, 'IR') ? $iban : (int) $iban,
                'Value' => (int) $value,
            ];
        }

        return [
            'Type' => $type,
            'MultiplexingRows' => $normalized,
        ];
    }
}
