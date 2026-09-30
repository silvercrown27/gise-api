{{-- Key/value summary box. $rows: ['Label' => 'Value', ...]; empty values are skipped. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 4px;background:#f6f9f7;border:1px solid #e3ebe6;border-radius:12px;">
    @foreach (array_filter($rows, fn ($value) => filled($value)) as $label => $value)
        <tr>
            <td style="padding:9px 16px;font-size:13px;color:#6b7a72;width:38%;vertical-align:top;{{ $loop->first ? 'padding-top:14px;' : '' }}{{ $loop->last ? 'padding-bottom:14px;' : '' }}">{{ $label }}</td>
            <td style="padding:9px 16px;font-size:14px;color:#0b1310;font-weight:600;vertical-align:top;{{ $loop->first ? 'padding-top:14px;' : '' }}{{ $loop->last ? 'padding-bottom:14px;' : '' }}">{{ $value }}</td>
        </tr>
    @endforeach
</table>
