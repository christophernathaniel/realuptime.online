<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background-color:#09111e;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#eaf0f9;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        {{ $preheader }}
    </div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#09111e;margin:0;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;">
                    <tr>
                        <td style="padding:0 20px 18px 20px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding-right:12px;vertical-align:middle;">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="width:8px;height:22px;border-radius:999px;background:#57c7c2;"></td>
                                                <td style="width:6px;"></td>
                                                <td style="width:8px;height:30px;border-radius:999px;background:#7c8cff;"></td>
                                                <td style="width:6px;"></td>
                                                <td style="width:8px;height:18px;border-radius:999px;background:#eaf0f9;"></td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td style="font-size:22px;font-weight:700;letter-spacing:-0.03em;color:#eaf0f9;">
                                        RealUptime
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 20px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#101a2c;border:1px solid #223149;border-radius:24px;overflow:hidden;">
                                <tr>
                                    <td style="padding:28px 28px 22px 28px;background:linear-gradient(135deg,#101a2c 0%,#13213a 65%,#17284a 100%);border-bottom:1px solid #223149;">
                                        <div style="font-size:11px;line-height:1.4;font-weight:700;letter-spacing:0.22em;text-transform:uppercase;color:#8fa0bf;">
                                            {{ $eyebrow }}
                                        </div>
                                        <div style="margin-top:14px;">
                                            <span style="display:inline-block;padding:7px 12px;border-radius:999px;background:#171d31;border:1px solid #2d3b59;font-size:12px;font-weight:700;color:#cfd8ec;">
                                                {{ $toneLabel }}
                                            </span>
                                        </div>
                                        <h1 style="margin:18px 0 0 0;font-size:34px;line-height:1.08;font-weight:700;letter-spacing:-0.05em;color:#eaf0f9;">
                                            {{ $title }}
                                        </h1>
                                        <p style="margin:16px 0 0 0;font-size:16px;line-height:1.7;color:#a8b7d2;">
                                            {{ $intro }}
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:24px 28px 18px 28px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            @foreach ($details as $detail)
                                                <tr>
                                                    <td style="padding:0 0 14px 0;border-bottom:1px solid #1c2840;">
                                                        <div style="font-size:12px;line-height:1.4;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#7283a0;">
                                                            {{ $detail['label'] }}
                                                        </div>
                                                        <div style="margin-top:8px;font-size:16px;line-height:1.6;color:#eaf0f9;">
                                                            {{ $detail['value'] }}
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>

                                        @if (! empty($buttonUrl) && ! empty($buttonLabel))
                                            <div style="padding-top:24px;">
                                                <a href="{{ $buttonUrl }}" style="display:inline-block;padding:14px 22px;border-radius:16px;background:#7c8cff;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;">
                                                    {{ $buttonLabel }}
                                                </a>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px 0 28px;text-align:center;font-size:12px;line-height:1.7;color:#7283a0;">
                            {{ $footnote }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
