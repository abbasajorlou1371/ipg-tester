<x-layouts.app title="نتیجه پرداخت سداد">
    @php
        $paid = $payment->status === \App\PaymentStatus::Paid;
        $failed = $payment->status === \App\PaymentStatus::Failed;
    @endphp

    <section class="flex flex-col gap-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <header class="flex flex-col gap-2">
            <p class="text-sm font-medium text-melli dark:text-sky-300">بانک ملی ایران</p>
            <h1 class="text-2xl font-bold">نتیجه پرداخت</h1>
        </header>

        @if ($payment->message)
            <p @class([
                'rounded-lg px-4 py-3 text-sm leading-6',
                'bg-emerald-50 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100' => $paid,
                'bg-red-50 text-red-900 dark:bg-red-950 dark:text-red-100' => $failed,
                'bg-amber-50 text-amber-950 dark:bg-amber-950 dark:text-amber-100' => ! $paid && ! $failed,
            ])>{{ $payment->message }}</p>
        @endif

        @if ($rows !== [])
            <dl class="flex flex-col gap-3">
                @foreach ($rows as $row)
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-sm text-slate-500 dark:text-slate-400">{{ $row['label'] }}</dt>
                        <dd class="text-left text-sm font-medium break-all" dir="ltr">{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        <div class="flex items-center justify-center gap-4">
            <a
                href="{{ route('payments.index') }}"
                class="text-sm font-medium text-melli hover:underline dark:text-sky-300"
            >
                فهرست پرداخت‌ها
            </a>
            <a
                href="{{ route('payments.create') }}"
                class="text-sm font-medium text-melli hover:underline dark:text-sky-300"
            >
                پرداخت جدید
            </a>
        </div>
    </section>
</x-layouts.app>
