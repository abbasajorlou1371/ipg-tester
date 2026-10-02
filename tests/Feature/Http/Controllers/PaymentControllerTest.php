<?php

use App\Models\Payment;
use App\PaymentStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config([
        'services.sadad.merchant_id' => '000000140339999',
        'services.sadad.terminal_id' => '24095674',
        'services.sadad.terminal_key' => 'MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0',
        'services.sadad.payment_request_url' => 'https://sadad.shaparak.ir/api/v0/Request/PaymentRequest',
        'services.sadad.verify_url' => 'https://sadad.shaparak.ir/api/v0/Advice/Verify',
        'services.sadad.purchase_url' => 'https://sadad.shaparak.ir/Purchase',
        'services.sadad.multiplexing.type' => 'Amount',
        'services.sadad.multiplexing.rows' => [
            ['IbanNumber' => 1],
        ],
    ]);
});

test('renders the payment form', function () {
    $this->get(route('payments.create'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('مبلغ پرداخت (ریال)')
        ->assertSee('شماره سفارش')
        ->assertSee('name="amount"', false)
        ->assertSee('name="order_id"', false)
        ->assertSee('id="generate-order-id"', false)
        ->assertSee('پرداخت');
});

test('rejects a payment when amount and order id are missing', function () {
    Http::preventStrayRequests();

    $this->post(route('payments.store'), [])
        ->assertInvalid([
            'amount' => 'مبلغ پرداخت الزامی است.',
            'order_id' => 'شماره سفارش الزامی است.',
        ]);

    $this->assertDatabaseCount('payments', 0);
    Http::assertNothingSent();
});

test('rejects an order id that is not a 16 digit number', function (string $orderId) {
    Http::preventStrayRequests();

    $this->post(route('payments.store'), [
        'amount' => 150000,
        'order_id' => $orderId,
    ])->assertInvalid([
        'order_id' => 'شماره سفارش باید یک عدد ۱۶ رقمی باشد.',
    ]);

    Http::assertNothingSent();
})->with([
    'too short' => '123',
    'leading zero' => '0123456789012345',
    'letters' => '123456789012345a',
]);

test('rejects an amount that is not a whole number of rials', function () {
    Http::preventStrayRequests();

    $this->post(route('payments.store'), [
        'amount' => '1500.5',
        'order_id' => '1234567890123456',
    ])->assertInvalid([
        'amount' => 'مبلغ پرداخت باید یک عدد صحیح باشد.',
    ]);

    Http::assertNothingSent();
});

test('stores an amount entered with thousand separators', function () {
    Http::preventStrayRequests();
    $this->travelTo('2026-10-02 12:00:00');

    Http::fake([
        'https://sadad.shaparak.ir/api/v0/Request/PaymentRequest' => Http::response([
            'ResCode' => 0,
            'Token' => 'test-token',
            'Description' => 'تراکنش موفق',
        ]),
    ]);

    $this->post(route('payments.store'), [
        'amount' => '150,000',
        'order_id' => '1234567890123456',
    ])->assertRedirect('https://sadad.shaparak.ir/Purchase?Token=test-token');

    $this->assertDatabaseHas('payments', [
        'order_id' => '1234567890123456',
        'amount' => 150000,
    ]);

    Http::assertSent(fn (Request $request) => $request['Amount'] === 150000);
});

test('rejects an amount below one rial', function () {
    Http::preventStrayRequests();

    $this->post(route('payments.store'), [
        'amount' => 0,
        'order_id' => '1234567890123456',
    ])->assertInvalid([
        'amount' => 'مبلغ پرداخت باید بیشتر از صفر باشد.',
    ]);

    Http::assertNothingSent();
});

test('rejects a duplicate order id', function () {
    Http::preventStrayRequests();

    Payment::factory()->create([
        'order_id' => '1234567890123456',
    ]);

    $this->post(route('payments.store'), [
        'amount' => 150000,
        'order_id' => '1234567890123456',
    ])->assertInvalid([
        'order_id' => 'شماره سفارش تکراری است.',
    ]);

    Http::assertNothingSent();
});

test('redirects to the ipg when the payment request succeeds', function () {
    Http::preventStrayRequests();
    $this->travelTo('2026-10-02 12:00:00');

    Http::fake([
        'https://sadad.shaparak.ir/api/v0/Request/PaymentRequest' => Http::response([
            'ResCode' => 0,
            'Token' => 'test-token',
            'Description' => 'تراکنش موفق',
        ]),
    ]);

    $this->post(route('payments.store'), [
        'amount' => 150000,
        'order_id' => '1234567890123456',
    ])->assertRedirect('https://sadad.shaparak.ir/Purchase?Token=test-token');

    $this->assertDatabaseHas('payments', [
        'order_id' => '1234567890123456',
        'amount' => 150000,
        'status' => 'redirected',
        'token' => 'test-token',
        'message' => 'تراکنش موفق',
        'res_code' => '0',
    ]);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://sadad.shaparak.ir/api/v0/Request/PaymentRequest'
            && $request['MerchantId'] === '000000140339999'
            && $request['TerminalId'] === '24095674'
            && $request['Amount'] === 150000
            && $request['OrderId'] === 1234567890123456
            && $request['LocalDateTime'] === '10/02/2026 3:30:00 pm'
            && $request['ReturnUrl'] === route('payments.callback')
            && $request['SignData'] === trim(file_get_contents(base_path('tests/Fixtures/sadad-payment-sign.txt')))
            && $request['MultiplexingData'] === [
                'Type' => 'Amount',
                'MultiplexingRows' => [
                    ['IbanNumber' => 1, 'Value' => 150000],
                ],
            ];
    });
});

test('sends configured percentage multiplexing rows', function () {
    Http::preventStrayRequests();
    $this->travelTo('2026-10-02 12:00:00');

    config([
        'services.sadad.multiplexing.type' => 'Percentage',
        'services.sadad.multiplexing.rows' => [
            ['IbanNumber' => 'IR820540102680020817909002', 'Value' => 60],
            ['IbanNumber' => 'IR490180000000000000000001', 'Value' => 40],
        ],
    ]);

    Http::fake([
        'https://sadad.shaparak.ir/api/v0/Request/PaymentRequest' => Http::response([
            'ResCode' => 0,
            'Token' => 'test-token',
            'Description' => 'تراکنش موفق',
        ]),
    ]);

    $this->post(route('payments.store'), [
        'amount' => 150000,
        'order_id' => '1234567890123456',
    ])->assertRedirect('https://sadad.shaparak.ir/Purchase?Token=test-token');

    Http::assertSent(function (Request $request) {
        return $request['MultiplexingData'] === [
            'Type' => 'Percentage',
            'MultiplexingRows' => [
                ['IbanNumber' => 'IR820540102680020817909002', 'Value' => 60],
                ['IbanNumber' => 'IR490180000000000000000001', 'Value' => 40],
            ],
        ];
    });
});

test('shows the payment request description when the gateway rejects the request', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://sadad.shaparak.ir/api/v0/Request/PaymentRequest' => Http::response([
            'ResCode' => 1026,
            'Token' => null,
            'Description' => 'شماره سفارش تراکنش نامعتبر است',
        ]),
    ]);

    $this->post(route('payments.store'), [
        'amount' => 150000,
        'order_id' => '1234567890123456',
    ])->assertRedirect();

    $payment = Payment::query()->first();

    $this->get(route('payments.show', $payment))
        ->assertOk()
        ->assertSee('شماره سفارش تراکنش نامعتبر است')
        ->assertSee('1026');

    expect($payment->status)->toBe(PaymentStatus::Failed);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'Advice/Verify'));
});

test('shows the configuration error when the terminal key is invalid', function () {
    Http::preventStrayRequests();

    config([
        'services.sadad.terminal_key' => 'not-a-key',
    ]);

    $this->post(route('payments.store'), [
        'amount' => 150000,
        'order_id' => '1234567890123456',
    ])->assertRedirect();

    $this->get(route('payments.show', Payment::query()->first()))
        ->assertOk()
        ->assertSee('کلید تراکنش سداد نامعتبر است.');

    Http::assertNothingSent();
});

test('shows a fallback when the payment request connection fails', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://sadad.shaparak.ir/api/v0/Request/PaymentRequest' => Http::failedConnection(),
    ]);

    $this->post(route('payments.store'), [
        'amount' => 150000,
        'order_id' => '1234567890123456',
    ])->assertRedirect();

    $payment = Payment::query()->first();

    $this->get(route('payments.show', $payment))
        ->assertOk()
        ->assertSee('پاسخی از درگاه پرداخت دریافت نشد.');

    expect($payment->status)->toBe(PaymentStatus::Failed);
});

test('escapes the gateway description on the result page', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://sadad.shaparak.ir/api/v0/Request/PaymentRequest' => Http::response([
            'ResCode' => 1002,
            'Description' => '<script>alert(1)</script>',
        ]),
    ]);

    $this->post(route('payments.store'), [
        'amount' => 150000,
        'order_id' => '1234567890123456',
    ]);

    $this->get(route('payments.show', Payment::query()->first()))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

test('shows the verify receipt when the callback is successful', function () {
    Http::preventStrayRequests();

    $payment = Payment::factory()->redirected()->create([
        'order_id' => '1234567890123456',
        'amount' => 150000,
        'token' => 'gateway-token',
    ]);

    Http::fake([
        'https://sadad.shaparak.ir/api/v0/Advice/Verify' => Http::response([
            'ResCode' => 0,
            'Amount' => 150000,
            'Description' => 'عملیات با موفقیت انجام شد',
            'RetrivalRefNo' => '987654321',
            'SystemTraceNo' => '123456',
            'OrderId' => 1234567890123456,
            'TransactionDate' => '1405/07/10',
            'CardHolderFullName' => 'علی رضایی',
        ]),
    ]);

    $this->post(route('payments.callback'), [
        'OrderId' => '1234567890123456',
        'Token' => 'gateway-token',
        'ResCode' => '0',
        'SwitchResCode' => '0',
        'HashedCardNo' => 'hashed-card',
        'PrimaryAccNo' => '603799******1234',
        'CardHolderFullName' => 'علی رضایی',
    ])->assertRedirect(route('payments.show', $payment));

    $this->get(route('payments.show', $payment))
        ->assertOk()
        ->assertSee('عملیات با موفقیت انجام شد')
        ->assertSee('987654321')
        ->assertSee('123456')
        ->assertSee('1405/07/10')
        ->assertSee('علی رضایی')
        ->assertSee('603799******1234')
        ->assertSee('150000')
        ->assertSee('1234567890123456');

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->retrieval_ref_no)->toBe('987654321')
        ->and($payment->system_trace_no)->toBe('123456');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://sadad.shaparak.ir/api/v0/Advice/Verify'
            && $request['Token'] === 'gateway-token'
            && $request['SignData'] === trim(file_get_contents(base_path('tests/Fixtures/sadad-verify-sign.txt')));
    });
});

test('shows the verify description when verification fails', function () {
    Http::preventStrayRequests();

    $payment = Payment::factory()->redirected()->create([
        'token' => 'gateway-token',
    ]);

    Http::fake([
        'https://sadad.shaparak.ir/api/v0/Advice/Verify' => Http::response([
            'ResCode' => -1,
            'Description' => 'پارامترهای ارسالی صحیح نیست',
        ]),
    ]);

    $this->post(route('payments.callback'), [
        'OrderId' => $payment->order_id,
        'Token' => 'gateway-token',
        'ResCode' => '0',
    ])->assertRedirect(route('payments.show', $payment));

    $this->get(route('payments.show', $payment))
        ->assertOk()
        ->assertSee('پارامترهای ارسالی صحیح نیست');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

test('treats an already verified transaction as successful', function () {
    Http::preventStrayRequests();

    $payment = Payment::factory()->redirected()->create([
        'token' => 'gateway-token',
        'amount' => 150000,
    ]);

    Http::fake([
        'https://sadad.shaparak.ir/api/v0/Advice/Verify' => Http::response([
            'ResCode' => 100,
            'Amount' => 150000,
            'Description' => 'درخواست تکراریست',
            'RetrivalRefNo' => '987654321',
            'SystemTraceNo' => '123456',
            'OrderId' => (int) $payment->order_id,
        ]),
    ]);

    $this->post(route('payments.callback'), [
        'OrderId' => $payment->order_id,
        'Token' => 'gateway-token',
        'ResCode' => '0',
    ]);

    $this->get(route('payments.show', $payment))
        ->assertOk()
        ->assertSee('درخواست تکراریست')
        ->assertSee('987654321');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('does not verify when the callback result is unsuccessful', function () {
    Http::preventStrayRequests();

    $payment = Payment::factory()->redirected()->create([
        'token' => 'gateway-token',
    ]);

    Http::fake([
        'https://sadad.shaparak.ir/*' => Http::response(['Description' => 'should-not-be-called'], 500),
    ]);

    $this->post(route('payments.callback'), [
        'OrderId' => $payment->order_id,
        'Token' => 'gateway-token',
        'ResCode' => '-1',
        'SwitchResCode' => '51',
    ])->assertRedirect(route('payments.show', $payment));

    $this->get(route('payments.show', $payment))
        ->assertOk()
        ->assertSee('-1')
        ->assertSee('51');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);

    Http::assertNothingSent();
});

test('verifies a gateway return sent as a get request', function () {
    Http::preventStrayRequests();

    $payment = Payment::factory()->redirected()->create([
        'token' => 'gateway-token',
    ]);

    Http::fake([
        'https://sadad.shaparak.ir/api/v0/Advice/Verify' => Http::response([
            'ResCode' => 0,
            'Description' => 'عملیات با موفقیت انجام شد',
            'RetrivalRefNo' => '987654321',
            'SystemTraceNo' => '123456',
            'Amount' => 150000,
            'OrderId' => (int) $payment->order_id,
        ]),
    ]);

    $this->get(route('payments.callback', [
        'OrderId' => $payment->order_id,
        'Token' => 'gateway-token',
        'ResCode' => '0',
    ]))->assertRedirect(route('payments.show', $payment));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('verifies the callback when plus signs in the token were decoded as spaces', function () {
    Http::preventStrayRequests();

    $payment = Payment::factory()->redirected()->create([
        'token' => 'abc+def/ghi=',
    ]);

    Http::fake([
        'https://sadad.shaparak.ir/api/v0/Advice/Verify' => Http::response([
            'ResCode' => 0,
            'Description' => 'عملیات با موفقیت انجام شد',
            'RetrivalRefNo' => '987654321',
            'SystemTraceNo' => '123456',
            'Amount' => 150000,
            'OrderId' => (int) $payment->order_id,
        ]),
    ]);

    $this->post(route('payments.callback'), [
        'OrderId' => $payment->order_id,
        'Token' => 'abc def/ghi=',
        'ResCode' => '0',
    ])->assertRedirect(route('payments.show', $payment));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);

    Http::assertSent(fn (Request $request) => $request['Token'] === 'abc+def/ghi=');
});

test('returns 404 when the callback token does not match', function () {
    Http::preventStrayRequests();

    $payment = Payment::factory()->redirected()->create([
        'token' => 'gateway-token',
    ]);

    Http::fake();

    $this->post(route('payments.callback'), [
        'OrderId' => $payment->order_id,
        'Token' => 'other-token',
        'ResCode' => '0',
    ])->assertNotFound();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Redirected);

    Http::assertNothingSent();
});

test('retries verification after a connection failure', function () {
    Http::preventStrayRequests();

    $payment = Payment::factory()->redirected()->create([
        'token' => 'gateway-token',
    ]);

    Http::fake([
        'https://sadad.shaparak.ir/api/v0/Advice/Verify' => Http::sequence()
            ->pushFailedConnection()
            ->push([
                'ResCode' => 0,
                'Description' => 'عملیات با موفقیت انجام شد',
                'RetrivalRefNo' => '987654321',
                'SystemTraceNo' => '123456',
                'Amount' => 150000,
                'OrderId' => (int) $payment->order_id,
            ]),
    ]);

    $this->post(route('payments.callback'), [
        'OrderId' => $payment->order_id,
        'Token' => 'gateway-token',
        'ResCode' => '0',
    ])->assertRedirect(route('payments.show', $payment));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);

    Http::assertSentCount(2);
});

test('shows a fallback when verification keeps failing to connect', function () {
    Http::preventStrayRequests();

    $payment = Payment::factory()->redirected()->create([
        'token' => 'gateway-token',
    ]);

    Http::fake([
        'https://sadad.shaparak.ir/api/v0/Advice/Verify' => Http::failedConnection(),
    ]);

    $this->post(route('payments.callback'), [
        'OrderId' => $payment->order_id,
        'Token' => 'gateway-token',
        'ResCode' => '0',
    ])->assertRedirect(route('payments.show', $payment));

    $this->get(route('payments.show', $payment))
        ->assertSee('پاسخی از سرویس تایید تراکنش دریافت نشد.');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Redirected);
});
