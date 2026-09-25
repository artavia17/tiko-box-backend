<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0; padding:0; background-color:#eef2fa; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2fa; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background-color:#ffffff; border-radius:24px; overflow:hidden; box-shadow:0 8px 24px rgba(7,20,49,0.08);">

                    {{-- Encabezado --}}
                    <tr>
                        <td style="background-color:#071431; padding:28px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding-right:12px;">
                                        <img src="{{ $message->embed(public_path('logo.png')) }}"
                                             alt="Tikabox" width="40"
                                             style="display:block; width:40px; height:auto; background-color:#ffffff; border-radius:10px; padding:4px;">
                                    </td>
                                    <td>
                                        <span style="font-size:26px; font-weight:800; color:#ffffff; letter-spacing:-0.5px;">tika<span style="color:#d71920;">box</span></span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Qué pasó --}}
                    <tr>
                        <td style="padding:36px 32px 0;">
                            <p style="margin:0 0 8px; font-size:12px; font-weight:700; color:#d71920; text-transform:uppercase; letter-spacing:0.5px;">
                                {{ $eyebrow }}
                            </p>
                            <h1 style="margin:0 0 12px; font-size:24px; font-weight:800; color:#071431;">
                                {{ $heading }}
                            </h1>
                            <p style="margin:0; font-size:16px; line-height:1.6; color:#1b3a73;">
                                {{ $intro }}
                            </p>
                        </td>
                    </tr>

                    {{-- Los datos, en el orden en que hacen falta --}}
                    <tr>
                        <td style="padding:24px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2fa; border-radius:16px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            @foreach ($rows as $label => $value)
                                                <tr>
                                                    <td style="font-size:14px; color:#1b3a73; padding-bottom:{{ $loop->last ? '0' : '8px' }}; padding-right:12px;" valign="top">
                                                        {{ $label }}
                                                    </td>
                                                    <td align="right" style="font-size:14px; font-weight:700; color:#071431; padding-bottom:{{ $loop->last ? '0' : '8px' }};" valign="top">
                                                        {{ $value }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Botón --}}
                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <a href="{{ $actionUrl }}"
                               style="display:block; background-color:#d71920; color:#ffffff; text-decoration:none; text-align:center; font-size:15px; font-weight:800; padding:14px 24px; border-radius:9999px;">
                                {{ $actionLabel }}
                            </a>
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td style="padding:16px 32px 32px;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#a9bce4; text-align:center;">
                                Aviso interno de Tikabox<br>
                                Lo recibís porque tu cuenta es de administración.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
