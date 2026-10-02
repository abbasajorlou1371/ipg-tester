<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\PaymentStatus;
use App\Services\Sadad\SadadGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PaymentController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'ResCode' => 'کد نتیجه',
        'Token' => 'توکن',
        'Amount' => 'مبلغ (ریال)',
        'OrderId' => 'شماره سفارش',
        'RetrivalRefNo' => 'شماره مرجع',
        'SystemTraceNo' => 'شماره پیگیری',
        'TransactionDate' => 'تاریخ تراکنش',
        'CardHolderFullName' => 'نام دارنده کارت',
        'SwitchResCode' => 'کد سوئیچ',
        'HashedCardNo' => 'هش شماره کارت',
        'PrimaryAccNo' => 'شماره کارت',
    ];

    public function create(): View
    {
        return view('payments.create');
    }

    public function store(StorePaymentRequest $request, SadadGateway $gateway): RedirectResponse
    {
        $payment = Payment::query()->create([
            'order_id' => $request->string('order_id')->toString(),
            'amount' => $request->integer('amount'),
            'status' => PaymentStatus::Pending,
        ]);

        try {
            $response = $gateway->requestPayment(
                $payment->amount,
                $payment->order_id,
                route('payments.callback'),
            );
        } catch (ConnectionException) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'message' => 'پاسخی از درگاه پرداخت دریافت نشد.',
            ]);

            return redirect()->route('payments.show', $payment);
        } catch (RuntimeException $exception) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('payments.show', $payment);
        }

        $succeeded = $gateway->paymentRequestSucceeded($response);

        $payment->update([
            'status' => $succeeded ? PaymentStatus::Redirected : PaymentStatus::Failed,
            'token' => is_string($response['Token'] ?? null) ? $response['Token'] : null,
            'message' => $this->description($response),
            'res_code' => $this->resCode($response),
            'request_response' => $response,
        ]);

        if ($succeeded) {
            return redirect()->away($gateway->purchaseUrl($response['Token']));
        }

        return redirect()->route('payments.show', $payment);
    }

    public function callback(Request $request, SadadGateway $gateway): RedirectResponse
    {
        $token = $this->callbackField($request, 'Token');
        $orderId = $this->callbackField($request, 'OrderId');
        $payment = $this->paymentFromCallback($token, $orderId);

        if ($payment === null) {
            abort(404);
        }

        $callback = array_filter([
            'OrderId' => $orderId,
            'HashedCardNo' => $this->callbackField($request, 'HashedCardNo'),
            'PrimaryAccNo' => $this->callbackField($request, 'PrimaryAccNo'),
            'SwitchResCode' => $this->callbackField($request, 'SwitchResCode'),
            'ResCode' => $this->callbackField($request, 'ResCode'),
            'CardHolderFullName' => $this->callbackField($request, 'CardHolderFullName'),
            'Token' => $token,
            'Description' => $this->callbackField($request, 'Description'),
        ], fn (string $value): bool => $value !== '');

        $payment->callback_response = $callback;

        if (! $gateway->callbackSucceeded($callback['ResCode'] ?? null)) {
            $payment->fill([
                'status' => PaymentStatus::Failed,
                'res_code' => $this->resCode($payment->callback_response),
                'message' => $this->description($payment->callback_response),
                'card_holder_full_name' => $callback['CardHolderFullName'] ?? null,
            ])->save();

            return redirect()->route('payments.show', $payment);
        }

        try {
            $verify = $gateway->verify($payment->token);
        } catch (ConnectionException) {
            $payment->fill([
                'message' => 'پاسخی از سرویس تایید تراکنش دریافت نشد.',
            ])->save();

            return redirect()->route('payments.show', $payment);
        }

        $succeeded = $gateway->verifySucceeded($verify['ResCode'] ?? null);

        $payment->fill([
            'status' => $succeeded ? PaymentStatus::Paid : PaymentStatus::Failed,
            'verify_response' => $verify,
            'res_code' => $this->resCode($verify),
            'message' => $this->description($verify),
            'retrieval_ref_no' => $this->stringValue($verify, 'RetrivalRefNo'),
            'system_trace_no' => $this->stringValue($verify, 'SystemTraceNo'),
            'transaction_date' => $this->stringValue($verify, 'TransactionDate'),
            'card_holder_full_name' => $this->stringValue($verify, 'CardHolderFullName')
                ?? ($callback['CardHolderFullName'] ?? null),
        ])->save();

        return redirect()->route('payments.show', $payment);
    }

    public function show(Payment $payment): View
    {
        return view('payments.show', [
            'payment' => $payment,
            'rows' => $this->resultRows($payment),
        ]);
    }

    private function paymentFromCallback(string $token, string $orderId): ?Payment
    {
        if ($token === '') {
            return null;
        }

        $payment = Payment::query()->where('token', $token)->first();

        if ($payment === null || ($orderId !== '' && $payment->order_id !== $orderId)) {
            return null;
        }

        return $payment;
    }

    /**
     * Form bodies decode a Base64 "+" as a space, which would not match the stored token.
     */
    private function callbackField(Request $request, string $key): string
    {
        foreach ($request->all() as $name => $value) {
            if (! is_string($name) || strcasecmp($name, $key) !== 0 || is_array($value)) {
                continue;
            }

            $normalized = trim((string) $value);

            if (strcasecmp($key, 'Token') === 0) {
                return str_replace(' ', '+', $normalized);
            }

            return $normalized;
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function description(array $payload): ?string
    {
        $description = $payload['Description'] ?? null;

        if (! is_string($description) || $description === '') {
            return null;
        }

        return $description;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resCode(array $payload): ?string
    {
        if (! array_key_exists('ResCode', $payload) || $payload['ResCode'] === null || $payload['ResCode'] === '') {
            return null;
        }

        return (string) $payload['ResCode'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringValue(array $payload, string $key): ?string
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
            return null;
        }

        return (string) $payload[$key];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function resultRows(Payment $payment): array
    {
        $rows = [];
        $seen = [];

        foreach ([$payment->verify_response, $payment->callback_response, $payment->request_response] as $source) {
            if (! is_array($source)) {
                continue;
            }

            foreach ($source as $key => $value) {
                if (! is_string($key) || isset($seen[$key]) || $key === 'Description' || is_array($value) || $value === null || $value === '') {
                    continue;
                }

                $seen[$key] = true;

                $rows[] = [
                    'label' => self::FIELD_LABELS[$key] ?? $key,
                    'value' => (string) $value,
                ];
            }
        }

        return $rows;
    }
}
