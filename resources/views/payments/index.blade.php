<x-layouts.app title="فهرست پرداخت‌ها" :wide="true">
    <section class="flex flex-col gap-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <header class="flex items-start justify-between gap-4">
            <div class="flex flex-col gap-2">
                <p class="text-sm font-medium text-melli dark:text-sky-300">بانک ملی ایران</p>
                <h1 class="text-2xl font-bold">فهرست پرداخت‌ها</h1>
            </div>
            <a
                href="{{ route('payments.create') }}"
                class="shrink-0 rounded-lg bg-melli px-3 py-2 text-sm font-medium text-white hover:bg-melli-dark dark:bg-sky-600 dark:hover:bg-sky-500"
            >
                پرداخت جدید
            </a>
        </header>

        @if ($payments->isEmpty())
            <p class="text-sm text-slate-600 dark:text-slate-400">هنوز پرداختی ثبت نشده است.</p>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($payments as $payment)
                    <a
                        href="{{ route('payments.show', $payment) }}"
                        class="flex flex-col gap-2 rounded-xl border border-slate-200 p-4 hover:border-melli dark:border-slate-800 dark:hover:border-sky-400"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium" dir="ltr">{{ $payment->order_id }}</span>
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-medium',
                                'bg-emerald-50 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100' => $payment->status === \App\PaymentStatus::Paid,
                                'bg-red-50 text-red-900 dark:bg-red-950 dark:text-red-100' => $payment->status === \App\PaymentStatus::Failed,
                                'bg-amber-50 text-amber-950 dark:bg-amber-950 dark:text-amber-100' => $payment->status === \App\PaymentStatus::Pending,
                                'bg-sky-50 text-sky-950 dark:bg-sky-950 dark:text-sky-100' => $payment->status === \App\PaymentStatus::Redirected,
                            ])>{{ $payment->status->label() }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3 text-sm text-slate-600 dark:text-slate-400">
                            <span><span dir="ltr">{{ number_format($payment->amount) }}</span> ریال</span>
                            <time datetime="{{ $payment->created_at->toAtomString() }}" dir="ltr">{{ $payment->created_at->format('Y-m-d H:i') }}</time>
                        </div>
                        @if ($payment->message)
                            <p class="text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $payment->message }}</p>
                        @endif
                    </a>
                @endforeach
            </div>

            {{ $payments->links() }}
        @endif
    </section>
</x-layouts.app>
