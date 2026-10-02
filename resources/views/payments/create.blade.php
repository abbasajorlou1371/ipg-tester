<x-layouts.app title="پرداخت آزمایشی سداد">
    <section class="flex flex-col gap-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <header class="flex flex-col gap-2">
            <p class="text-sm font-medium text-melli dark:text-sky-300">بانک ملی ایران</p>
            <h1 class="text-2xl font-bold">آزمایش درگاه پرداخت سداد</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">مبلغ را به ریال وارد کنید و شماره سفارش ۱۶ رقمی بسازید.</p>
        </header>

        <form method="POST" action="{{ route('payments.store') }}" class="flex flex-col gap-5">
            @csrf

            <div class="flex flex-col gap-2">
                <label for="amount" class="text-sm font-medium">مبلغ پرداخت (ریال)</label>
                <input
                    id="amount"
                    name="amount"
                    type="text"
                    inputmode="numeric"
                    dir="ltr"
                    value="{{ old('amount') }}"
                    autocomplete="off"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-slate-900 outline-none focus:border-melli dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                >
                @error('amount')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label for="order_id" class="text-sm font-medium">شماره سفارش</label>
                <div class="flex items-center gap-2">
                    <input
                        id="order_id"
                        name="order_id"
                        type="text"
                        inputmode="numeric"
                        maxlength="16"
                        dir="ltr"
                        value="{{ old('order_id') }}"
                        autocomplete="off"
                        class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-slate-900 outline-none focus:border-melli dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                    >
                    <button
                        id="generate-order-id"
                        type="button"
                        class="shrink-0 rounded-lg border border-melli px-3 py-2 text-sm font-medium text-melli hover:bg-slate-50 dark:border-sky-400 dark:text-sky-300 dark:hover:bg-slate-800"
                    >
                        تولید
                    </button>
                </div>
                @error('order_id')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="rounded-lg bg-melli px-4 py-2.5 cursor-pointer font-medium text-white hover:bg-melli-dark dark:bg-sky-600 dark:hover:bg-sky-500"
            >
                پرداخت
            </button>
        </form>
    </section>

    <script>
        const amountInput = document.getElementById('amount');

        const formatAmount = () => {
            const caret = amountInput.selectionStart ?? amountInput.value.length;
            const digitsBeforeCaret = amountInput.value.slice(0, caret).replace(/\D/g, '').length;
            const digits = amountInput.value.replace(/\D/g, '');

            amountInput.value = digits === '' ? '' : Number(digits).toLocaleString('en-US');

            let nextCaret = 0;
            let seenDigits = 0;

            while (nextCaret < amountInput.value.length && seenDigits < digitsBeforeCaret) {
                if (/\d/.test(amountInput.value.charAt(nextCaret))) {
                    seenDigits += 1;
                }

                nextCaret += 1;
            }

            amountInput.setSelectionRange(nextCaret, nextCaret);
        };

        amountInput.addEventListener('input', formatAmount);
        formatAmount();

        document.getElementById('generate-order-id').addEventListener('click', () => {
            const firstDigit = Math.floor(Math.random() * 9) + 1;
            let orderId = String(firstDigit);

            for (let index = 0; index < 15; index += 1) {
                orderId += String(Math.floor(Math.random() * 10));
            }

            document.getElementById('order_id').value = orderId;
        });
    </script>
</x-layouts.app>
