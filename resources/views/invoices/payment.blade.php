{{-- Course registration invoice, rendered to PDF by dompdf (CSS 2.1 only - tables, no flexbox). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $number }}</title>
    <style>
        @page { margin: 36px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0b1310; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #6b7a72; }
        .title { font-size: 24px; font-weight: bold; text-align: right; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #6b7a72; margin-bottom: 4px; }
        .box { border: 1px solid #dfe7e1; border-radius: 8px; padding: 12px 14px; vertical-align: top; }
        .items th { background: #eef3ef; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #3d4a43; padding: 8px 10px; }
        .items td { padding: 10px; border-bottom: 1px solid #dfe7e1; vertical-align: top; }
        .right { text-align: right; }
        .total td { padding: 10px; font-size: 13px; font-weight: bold; }
        .stamp { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 10px; font-weight: bold; letter-spacing: 1px; }
        .paid { background: #dff3e7; color: #0c6b44; }
        .refunded { background: #e6ecfb; color: #2445a8; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="vertical-align: top;">
                <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('brand/logo.png'))) }}" alt="GISE Africa" style="height: 56px;">
                <div class="muted" style="margin-top: 8px;">
                    Bimz Plaza, CFSK road, Utawala<br>
                    Nairobi, Kenya<br>
                    info@giseafrica.com · +254 727 427839
                </div>
            </td>
            <td style="vertical-align: top; text-align: right;">
                <div class="title">Invoice</div>
                <div style="margin-top: 4px;"><strong>{{ $number }}</strong></div>
                <div class="muted">Issued {{ $issuedAt?->format('j F Y') }}</div>
                <div style="margin-top: 8px;">
                    @if ($refunded)
                        <span class="stamp refunded">REFUNDED</span>
                    @else
                        <span class="stamp paid">PAID</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table style="margin-top: 28px;">
        <tr>
            <td class="box" style="width: 50%;">
                <div class="label">Billed to</div>
                <strong>{{ $learner['name'] }}</strong><br>
                {{ $learner['email'] }}
                @if ($learner['phone'])<br>{{ $learner['phone'] }}@endif
            </td>
            <td style="width: 16px;"></td>
            <td class="box" style="width: 50%;">
                <div class="label">Payment</div>
                <table>
                    <tr><td class="muted" style="padding: 1px 0;">Paid on</td><td class="right" style="padding: 1px 0;">{{ $issuedAt?->format('j M Y, H:i') }} EAT</td></tr>
                    <tr><td class="muted" style="padding: 1px 0;">Method</td><td class="right" style="padding: 1px 0;">{{ $method }}</td></tr>
                    <tr><td class="muted" style="padding: 1px 0;">Reference</td><td class="right" style="padding: 1px 0;">{{ $payment->reference ?? '-' }}</td></tr>
                    @if ($payment->gateway_transaction_id)
                        <tr><td class="muted" style="padding: 1px 0;">Paystack ID</td><td class="right" style="padding: 1px 0;">{{ $payment->gateway_transaction_id }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 28px;">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right" style="width: 50px;">Qty</th>
                <th class="right" style="width: 130px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $item['title'] }}</strong>
                    @if ($item['cohort'])<br><span class="muted">{{ $item['cohort'] }}</span>@endif
                    @if ($item['licences'])<br><span class="muted">Includes software licences</span>@endif
                </td>
                <td class="right">1</td>
                <td class="right">{{ $amount }}</td>
            </tr>
        </tbody>
    </table>

    <table style="margin-top: 6px;">
        <tr class="total">
            <td class="right">Total {{ $refunded ? 'refunded' : 'paid' }}</td>
            <td class="right" style="width: 130px;">{{ $amount }}</td>
        </tr>
    </table>

    <p class="muted" style="margin-top: 40px; font-size: 9px;">
        This invoice was generated automatically for a course registration paid through Paystack and is valid without a signature.
        Questions? Email info@giseafrica.com and quote {{ $number }}.
    </p>
</body>
</html>
