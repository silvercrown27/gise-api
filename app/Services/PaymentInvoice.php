<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ScholarUser;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Invoices for paid course registrations. Only completed (or later
 * refunded) payments have one - there is nothing to invoice before Paystack
 * confirms the charge.
 */
class PaymentInvoice
{
    public const INVOICEABLE = ['completed', 'refunded'];

    private const TIMEZONE = 'Africa/Nairobi';

    public static function isInvoiceable(Payment $payment): bool
    {
        return in_array($payment->status, self::INVOICEABLE, true);
    }

    /**
     * Give the payment its permanent invoice number if it doesn't have one:
     * INV-{year paid}-{sequence}, counting up within the year. Row-locked so
     * two payments settling at once can't take the same number.
     */
    public static function assignNumber(Payment $payment): string
    {
        if ($payment->invoice_number) {
            return $payment->invoice_number;
        }

        return DB::transaction(function () use ($payment) {
            $fresh = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (!$fresh->invoice_number) {
                $year = ($fresh->paid_at ?? now())->copy()->timezone(self::TIMEZONE)->format('Y');
                $prefix = "INV-{$year}-";

                $last = Payment::withTrashed()
                    ->where('invoice_number', 'like', $prefix . '%')
                    ->lockForUpdate()
                    ->orderByDesc('invoice_number')
                    ->value('invoice_number');

                $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
                $fresh->forceFill(['invoice_number' => $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT)])->save();
            }

            $payment->invoice_number = $fresh->invoice_number;

            return $fresh->invoice_number;
        });
    }

    /** The PDF, ready to stream to the learner. */
    public static function pdf(Payment $payment)
    {
        self::assignNumber($payment);
        $payment->loadMissing(['course', 'cohort', 'learner']);

        // dompdf caches font metrics here; a fresh deploy has no such folder
        // and rendering fails without it.
        foreach ([config('dompdf.options.font_dir'), config('dompdf.options.font_cache')] as $dir) {
            if ($dir && !is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        return Pdf::loadView('invoices.payment', self::viewData($payment))->setPaper('a4');
    }

    public static function filename(Payment $payment): string
    {
        return 'GISE-Africa-' . self::assignNumber($payment) . '.pdf';
    }

    private static function viewData(Payment $payment): array
    {
        $receipt = $payment->gateway_response ?? [];
        $cohort = $payment->cohort;
        $profile = ScholarUser::find($payment->learner_id);
        $paidAt = ($payment->paid_at ?? $payment->updated_at)?->copy()->timezone(self::TIMEZONE);

        $method = match ($payment->channel ?? $payment->payment_method) {
            'card' => trim(ucfirst($receipt['card_type'] ?? 'Card') . (isset($receipt['last4']) ? ' ending ' . $receipt['last4'] : '')),
            'mobile_money' => 'Mobile money',
            'bank', 'bank_transfer' => 'Bank transfer',
            'ussd' => 'USSD',
            null => '-',
            default => ucwords(str_replace('_', ' ', $payment->channel ?? $payment->payment_method)),
        };

        $cohortLine = null;
        if ($cohort) {
            $dates = $cohort->start_date?->format('j M Y') . ($cohort->end_date ? ' - ' . $cohort->end_date->format('j M Y') : '');
            $where = $cohort->mode === 'physical'
                ? trim(implode(', ', array_filter([$cohort->location_city, $cohort->location_country]))) ?: 'Physical'
                : 'Virtual';
            $cohortLine = "{$cohort->label} cohort · {$dates} · {$where}";
        }

        return [
            'payment'    => $payment,
            'number'     => $payment->invoice_number,
            'issuedAt'   => $paidAt,
            'learner'    => [
                'name'  => $payment->learner?->name,
                'email' => $payment->learner?->email,
                'phone' => $profile?->phone,
            ],
            'item'       => [
                'title'    => $payment->course?->title ?? 'Course registration',
                'cohort'   => $cohortLine,
                'licences' => $payment->with_licences,
            ],
            'amount'     => self::money($payment->amount, $payment->currency),
            'method'     => $method,
            'refunded'   => $payment->status === 'refunded',
        ];
    }

    private static function money(int $amount, ?string $currency): string
    {
        return strtoupper($currency ?? 'USD') . ' ' . number_format($amount, 2);
    }
}
