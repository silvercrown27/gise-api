{{-- Shared shell for GISE emails. Table layout + inline styles so it renders in every mail client. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background:#eef3ef;font-family:Helvetica,Arial,sans-serif;color:#0b1310;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3ef;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;border:1px solid #dfe7e1;">
                    <tr>
                        <td style="padding:28px 32px 0;">
                            <p style="margin:0;font-size:18px;font-weight:700;color:#0c6b44;">GISE</p>
                            <p style="margin:2px 0 0;font-size:12px;color:#6b7a72;">Global Institute for Skills Excellence</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px 32px;font-size:15px;line-height:1.6;">
                            @yield('content')
                        </td>
                    </tr>
                </table>
                <p style="margin:16px 0 0;font-size:12px;color:#6b7a72;">
                    You're receiving this because of activity on your GISE account.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
