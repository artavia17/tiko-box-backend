{{-- Aviso al cliente. Mismo vestido que los demás correos, sin datos de
     paquete: sirve para cualquier novedad que tenga un texto y un botón. --}}
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

                    <tr>
                        <td style="padding:36px 32px 0;">
                            @if (! empty($eyebrow))
                                <p style="margin:0 0 8px; font-size:12px; font-weight:700; color:#1b3a73; text-transform:uppercase; letter-spacing:0.5px;">
                                    {{ $eyebrow }}
                                </p>
                            @endif

                            <h1 style="margin:0 0 12px; font-size:24px; font-weight:800; color:#071431;">
                                {{ $heading }}
                            </h1>

                            <p style="margin:0; font-size:16px; line-height:1.6; color:#1b3a73;">
                                {{ $intro }}
                            </p>
                        </td>
                    </tr>

                    @if (! empty($quote))
                        <tr>
                            <td style="padding:24px 32px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2fa; border-radius:16px;">
                                    <tr>
                                        <td style="padding:20px 24px; font-size:15px; line-height:1.6; color:#071431;">
                                            {{ $quote }}
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:28px 32px 36px;">
                            <a href="{{ $actionUrl }}"
                               style="display:inline-block; background-color:#d71920; color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; padding:14px 28px; border-radius:999px;">
                                {{ $actionLabel }}
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#eef2fa; padding:20px 32px; font-size:12px; color:#1b3a73;">
                            Este correo es automático. Respondé desde la plataforma para que tu mensaje llegue a soporte.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
