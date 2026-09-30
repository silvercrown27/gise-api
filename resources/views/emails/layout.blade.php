{{-- Shared shell for every GISE Africa email: logo header, content, then a footer with contact details and policy links.
     Table layout + inline styles so it renders in every mail client. --}}
@php
    $site = rtrim(config('app.frontend_url'), '/');
    // Widest the text may get, in pixels, on a large screen. Everything else is full width.
    $width = 880;
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
<body style="margin:0;padding:0;background:#ffffff;font-family:Helvetica,Arial,sans-serif;color:#0b1310;-webkit-text-size-adjust:100%;">
    {{-- Preview text shown next to the subject in the inbox. --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">@yield('preheader')</div>

    {{-- A full-width page, not a card: the colour bands run edge to edge and the text
         fills the window, held to a comfortable reading width ($width) on very wide screens. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#ffffff;">
        {{-- Header --}}
        <tr>
            <td style="height:6px;line-height:6px;font-size:0;background:#0c6b44;">&nbsp;</td>
        </tr>
        <tr>
            <td align="center" style="padding:22px 28px;border-bottom:1px solid #e3ebe6;background:#ffffff;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:{{ $width }}px;">
                    <tr>
                        <td align="left">
                            <a href="{{ $site }}" style="text-decoration:none;">
                                {{-- Hosted by the website (public/brand); the alt text stands in when images are blocked. --}}
                                <img src="{{ $site }}/brand/logo-email.png" width="139" height="55" alt="GISE Africa - Global Institute For Skills And Excellence Africa" style="display:block;border:0;outline:none;height:55px;width:139px;color:#0c6b44;font-size:16px;font-weight:700;">
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- Content --}}
        <tr>
            <td align="center" style="padding:36px 28px 44px;background:#ffffff;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:{{ $width }}px;">
                    <tr>
                        <td align="left" style="font-size:16px;line-height:1.7;color:#0b1310;">
                            @yield('content')
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- Footer --}}
        <tr>
            <td align="center" style="padding:28px 28px 32px;background:#f3f7f4;border-top:1px solid #e3ebe6;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:{{ $width }}px;">
                    <tr>
                        <td align="left" style="font-size:13px;line-height:1.75;color:#6b7a72;">
                            <p style="margin:0 0 12px;">
                                @foreach ($policies as $label => $path)
                                    <a href="{{ $site . $path }}" style="color:#0c6b44;text-decoration:none;font-weight:600;">{{ $label }}</a>@if (! $loop->last)<span style="color:#b7c4bc;"> &nbsp;|&nbsp; </span>@endif
                                @endforeach
                            </p>
                            <p style="margin:0 0 12px;">
                                @hasSection('reason')@yield('reason')@else You're receiving this because of activity on your GISE Africa account.@endif
                            </p>
                            <p style="margin:0;">
                                <strong style="color:#3d4a43;">Global Institute For Skills And Excellence Africa</strong><br>
                                Bimz Plaza, CFSK road, Utawala, Nairobi, Kenya<br>
                                <a href="mailto:info@giseafrica.com" style="color:#6b7a72;">info@giseafrica.com</a> &middot; +254 727 427839
                            </p>
                            <p style="margin:14px 0 0;font-size:12px;color:#8a978f;">&copy; {{ now()->year }} GISE Africa</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
