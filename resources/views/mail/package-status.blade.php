<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu paquete {{ $package->tracking_number }}</title>
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
                            <h1 style="margin:0 0 12px; font-size:24px; font-weight:800; color:#071431;">
                                Hola, {{ $firstName }}
                            </h1>
                            <p style="margin:0; font-size:16px; line-height:1.6; color:#1b3a73;">
                                {{ $description }}
                            </p>
                        </td>
                    </tr>

                    {{-- Datos del paquete --}}
                    <tr>
                        <td style="padding:24px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2fa; border-radius:16px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        <p style="margin:0 0 4px; font-size:12px; font-weight:700; color:#1b3a73; text-transform:uppercase; letter-spacing:0.5px;">
                                            Número de rastreo
                                        </p>
                                        <p style="margin:0 0 16px; font-size:18px; font-weight:800; color:#071431;">
                                            {{ $package->tracking_number }}
                                        </p>

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="font-size:14px; color:#1b3a73; padding-bottom:6px;">Peso</td>
                                                <td align="right" style="font-size:14px; font-weight:700; color:#071431; padding-bottom:6px;">
                                                    {{ rtrim(rtrim(number_format($package->weight_lb, 2), '0'), '.') }} lb
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:14px; color:#1b3a73;">Total</td>
                                                <td align="right" style="font-size:14px; font-weight:800; color:#d71920;">
                                                    ${{ number_format($package->total, 2) }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Constancia de entrega --}}
                    @if ($package->status === 'entregado' && $package->delivered_to_name)
                        <tr>
                            <td style="padding:20px 32px 0;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #d5def2; border-radius:16px;">
                                    <tr>
                                        <td style="padding:16px 20px;">
                                            <p style="margin:0 0 4px; font-size:12px; font-weight:700; color:#1b3a73; text-transform:uppercase; letter-spacing:0.5px;">
                                                Lo recibió
                                            </p>
                                            <p style="margin:0; font-size:16px; font-weight:800; color:#071431;">
                                                {{ $package->delivered_to_name }}
                                            </p>
                                            @if ($package->delivered_to_identification)
                                                <p style="margin:4px 0 0; font-size:13px; color:#1b3a73;">
                                                    Cédula {{ $package->delivered_to_identification }}
                                                </p>
                                            @endif
                                            <p style="margin:8px 0 0; font-size:12px; color:#a9bce4;">
                                                Firmado el {{ optional($package->delivered_at)->format('d/m/Y \a \l\a\s H:i') }}
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    {{-- Recorrido --}}
                    <tr>
                        <td style="padding:24px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                @foreach ($steps as $step)
                                    <tr>
                                        <td width="28" valign="top" style="padding-bottom:{{ $loop->last ? '0' : '14px' }};">
                                            <div style="width:14px; height:14px; border-radius:9999px; background-color:{{ $step['done'] ? '#d71920' : '#d5def2' }}; margin-top:3px;"></div>
                                        </td>
                                        <td style="padding-bottom:{{ $loop->last ? '0' : '14px' }};">
                                            <span style="font-size:15px; font-weight:{{ $step['current'] ? '800' : '600' }}; color:{{ $step['done'] ? '#071431' : '#a9bce4' }};">
                                                {{ $step['label'] }}
                                            </span>
                                            @if ($step['current'])
                                                <span style="display:inline-block; margin-left:8px; padding:2px 8px; border-radius:9999px; background-color:#fdecec; font-size:11px; font-weight:800; color:#b0121a;">
                                                    Ahora
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- Botón --}}
                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <a href="{{ $dashboardUrl }}"
                               style="display:block; background-color:#d71920; color:#ffffff; text-decoration:none; text-align:center; font-size:15px; font-weight:800; padding:14px 24px; border-radius:9999px;">
                                Ver mis paquetes
                            </a>
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td style="padding:16px 32px 32px;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#a9bce4; text-align:center;">
                                Tikabox · Tu casillero en Miami<br>
                                Si tenés dudas con este paquete, respondé este correo.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
