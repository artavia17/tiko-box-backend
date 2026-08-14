<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperá tu contraseña</title>
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
                        <td style="padding:36px 32px 8px;">
                            <h1 style="margin:0 0 12px; font-size:24px; font-weight:800; color:#071431;">
                                Hola, {{ $firstName }}
                            </h1>
                            <p style="margin:0; font-size:16px; line-height:1.6; color:#1b3a73;">
                                Recibimos una solicitud para cambiar la contraseña de tu cuenta.
                                Usá este código para crear una nueva:
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 32px;">
                            <div style="background-color:#eef2fa; border-radius:16px; padding:24px; text-align:center;">
                                <span style="display:inline-block; font-size:44px; font-weight:800; letter-spacing:14px; color:#d71920; padding-left:14px;">
                                    {{ $code }}
                                </span>
                            </div>
                            <p style="margin:16px 0 0; font-size:13px; line-height:1.6; color:#1b3a73; text-align:center;">
                                El código vence en {{ $minutes }} minutos.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:8px 32px 32px;">
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#1b3a73;">
                                Si no pediste este cambio, ignorá este mensaje: tu contraseña sigue igual.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#071431; padding:20px 32px; text-align:center;">
                            <p style="margin:0; font-size:12px; color:#a9bce4;">
                                Tikabox · Tu conexión entre USA y Costa Rica
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
