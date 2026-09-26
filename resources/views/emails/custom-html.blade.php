<!DOCTYPE html>
@php($emailBranding = app(\App\Services\EmailBranding::class)->presentation($includeBranding ?? true))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>{{ config('brand.name') }}</title>
</head>
<body style="margin:0;background:#f4efe3;color:#172033;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#f4efe3;">
    <tr>
        <td align="center" style="padding:28px 12px;">
            <table role="presentation" width="570" cellpadding="0" cellspacing="0" style="width:100%;max-width:570px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 28px rgba(23,32,51,.08);">
                @if ($emailBranding['show_header'])
                    <tr>
                        <td align="center" style="background:#172033;padding:24px;">
                            {!! $emailBranding['header_html'] !!}
                        </td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:32px;font-size:16px;line-height:1.65;">
                        {!! $html !!}
                    </td>
                </tr>
                @isset($unsubscribeUrl)
                    <tr>
                        <td align="center" style="border-top:1px solid #e6dfd1;padding:18px 28px;color:#6b7280;font-size:12px;line-height:1.6;">
                            You’re receiving this because you’re on a {{ config('brand.name') }} mailing list.<br>
                            <a href="{{ $unsubscribeUrl }}" style="color:#6b7280;text-decoration:underline;">Unsubscribe</a> at any time.
                        </td>
                    </tr>
                @endisset
                @if ($emailBranding['show_footer'])
                    <tr>
                        <td align="center" style="border-top:1px solid #e6dfd1;padding:22px 28px;color:#6b7280;font-size:12px;line-height:1.6;">
                            {!! $emailBranding['footer_html'] !!}
                        </td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
</body>
</html>
