{{-- Shared shell for every GISE Africa email: logo header, content, then a footer with contact details and policy links.
     Table layout + inline styles so it renders in every mail client. --}}
@php
    $site = rtrim(config('app.frontend_url'), '/');
    $policies = [
        'Privacy Policy' => '/policies/privacy-policy',
        'Terms of Use' => '/policies/terms-of-use',
        'Cookie Policy' => '/policies/cookie-policy',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background:#eef3ef;font-family:Helvetica,Arial,sans-serif;color:#0b1310;">
    {{-- Preview text shown next to the subject in the inbox. --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">@yield('preheader')</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3ef;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;border:1px solid #dfe7e1;overflow:hidden;">
                    {{-- Header --}}
                    <tr>
                        <td style="height:5px;line-height:5px;font-size:0;background:#0c6b44;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px 20px;border-bottom:1px solid #eef3ef;">
                            <a href="{{ $site }}" style="text-decoration:none;">
                                {{-- Hosted by the website (public/brand); the alt text stands in when images are blocked. --}}
                                <img src="{{ $site }}/brand/logo-email.png" width="111" height="44" alt="GISE Africa - Global Institute For Skills And Excellence Africa" style="display:block;border:0;outline:none;height:44px;width:111px;color:#0c6b44;font-size:16px;font-weight:700;">
                            </a>
                        </td>
                    </tr>
                    {{-- Content --}}
                    <tr>
                        <td style="padding:28px 32px 32px;font-size:15px;line-height:1.6;">
                            @yield('content')
                        </td>
                    </tr>
                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 32px 24px;background:#f6f9f7;border-top:1px solid #eef3ef;font-size:12px;line-height:1.7;color:#6b7a72;">
                            <p style="margin:0 0 10px;">
                                @foreach ($policies as $label => $path)
                                    <a href="{{ $site . $path }}" style="color:#0c6b44;text-decoration:none;font-weight:600;">{{ $label }}</a>@if (! $loop->last)<span style="color:#b7c4bc;"> &nbsp;|&nbsp; </span>@endif
                                @endforeach
                            </p>
                            <p style="margin:0 0 10px;">
                                @hasSection('reason')@yield('reason')@else You're receiving this because of activity on your GISE Africa account.@endif
                            </p>
                            <p style="margin:0;">
                                <strong style="color:#3d4a43;">Global Institute For Skills And Excellence Africa</strong><br>
                                Bimz Plaza, CFSK road, Utawala, Nairobi, Kenya<br>
                                <a href="mailto:info@giseafrica.com" style="color:#6b7a72;">info@giseafrica.com</a> &middot; +254 727 427839
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin:14px 0 0;font-size:11px;color:#8a978f;">&copy; {{ now()->year }} GISE Africa</p>
            </td>
        </tr>
    </table>
</body>
</html>
