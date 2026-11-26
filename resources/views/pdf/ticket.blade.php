<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <title>Ulaznica - {{ $ticket['event_title'] }}</title>

    <style>
        @page {
            margin: 35px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1e293b;
            background: #ffffff;
            font-size: 12px;
        }

        .ticket {
            border: 2px solid #1e293b;
            border-radius: 14px;
            padding: 28px;
        }

        .header {
            border-bottom: 2px dashed #cbd5e1;
            padding-bottom: 20px;
            margin-bottom: 22px;
        }

        .brand {
            color: #64748b;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        h1 {
            font-size: 25px;
            margin: 12px 0 5px;
            color: #0f172a;
        }

        .subtitle {
            color: #64748b;
            font-size: 11px;
        }

        .details {
            width: 100%;
            border-collapse: collapse;
        }

        .details td {
            width: 50%;
            padding: 13px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .label {
            display: block;
            color: #64748b;
            font-size: 9px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .value {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }

        .bottom {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px dashed #cbd5e1;
            text-align: center;
        }

        .qr {
            width: 165px;
            height: 165px;
        }

        .code {
            margin-top: 12px;
            font-size: 9px;
            color: #64748b;
            word-wrap: break-word;
        }

        .notice {
            margin-top: 18px;
            color: #64748b;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <div class="brand">Evently · Ulaznica</div>

            <h1>{{ $ticket['event_title'] }}</h1>

            <div class="subtitle">
                Vaša digitalna ulaznica za događaj
            </div>
        </div>

        <table class="details">
            <tr>
                <td>
                    <span class="label">Datum i vrijeme</span>
                    <span class="value">
                        {{ $ticket['event_starts_at'] ?? 'Nije navedeno' }}
                    </span>
                </td>

                <td>
                    <span class="label">Lokacija</span>
                    <span class="value">
                        {{ $ticket['event_location'] ?? 'Nije navedeno' }}
                    </span>
                </td>
            </tr>

            <tr>
                <td>
                    <span class="label">Sjedište</span>
                    <span class="value">{{ $ticket['seat'] }}</span>
                </td>

                <td>
                    <span class="label">Cijena</span>
                    <span class="value">{{ $ticket['price'] }}</span>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <span class="label">Kategorije</span>
                    <span class="value">
                        {{ $ticket['categories']->implode(', ') ?: 'Nema kategorije' }}
                    </span>
                </td>
            </tr>
        </table>

        <div class="bottom">
            <img class="qr" src="{{ $qrCode }}" alt="QR kod ulaznice">

            <div class="code">
                Identifikacijski kod ulaznice:<br>
                {{ $ticket['ticket_uuid'] }}
            </div>

            <div class="notice">
                Sačuvajte ovu ulaznicu i pokažite QR kod prilikom ulaska na događaj.
            </div>
        </div>
    </div>
</body>
</html>
