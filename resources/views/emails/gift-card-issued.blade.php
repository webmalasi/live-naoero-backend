<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Naoero Airlines gift card</title>
</head>
<body style="margin:0;padding:0;background:#eef2f8;font-family:Helvetica,Arial,sans-serif;color:#012169;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f8;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 28px 8px;font-size:13px;letter-spacing:3px;font-weight:bold;color:#012169;">NAOERO AIRLINES</td>
                </tr>
                <tr>
                    <td style="padding:4px 28px 20px;font-size:22px;font-weight:bold;">
                        @if ($card->recipient_name)
                            {{ $card->recipient_name }}, {{ $card->buyer_name }} sent you a gift
                        @else
                            Your gift card is ready
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 28px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#012169;border-radius:16px;color:#ffffff;">
                            <tr>
                                <td style="padding:22px 22px 6px;font-size:12px;letter-spacing:2px;color:#ffc631;">GIFT CARD</td>
                                <td align="right" style="padding:22px 22px 6px;font-size:26px;font-weight:bold;">{{ $card->formatted_amount }}</td>
                            </tr>
                            <tr>
                                <td colspan="2" style="padding:18px 22px 6px;font-size:11px;letter-spacing:2px;color:#b9c4dd;">CODE</td>
                            </tr>
                            <tr>
                                <td colspan="2" style="padding:0 22px 22px;font-family:'Courier New',monospace;font-size:22px;font-weight:bold;letter-spacing:2px;">{{ $card->voucher_code }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @if ($card->personal_message)
                    <tr>
                        <td style="padding:20px 28px 0;">
                            <div style="border-left:4px solid #ffc631;padding:8px 14px;font-size:15px;font-style:italic;color:#33415c;">“{{ $card->personal_message }}”</div>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:20px 28px;font-size:14px;line-height:1.6;color:#33415c;">
                        Use this code with Naoero Airlines for flights, extras and more.
                        @if ($card->expires_at)
                            It's valid until <strong>{{ $card->expires_at->format('j F Y') }}</strong>.
                        @endif
                        Keep it safe: anyone with the code can use its balance.
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 28px 28px;font-size:12px;color:#8a94a8;">Order {{ $card->order_number }} · Naoero Airlines, Republic of Nauru</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
