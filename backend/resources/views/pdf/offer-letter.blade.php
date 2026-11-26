<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $letter['title'] }} — {{ $letter['candidate'] }}</title>
    <style>
        @page { margin: 1.8cm 2.2cm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; line-height: 1.4; color: #000; }
        p { margin: 0 0 8px; }
        .header { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 14px; }
        .header, .signatures, .terms { border-collapse: collapse; }
        .header td { vertical-align: middle; padding: 0 0 8px; }
        .company { font-size: 15pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .title { font-size: 8.5pt; text-transform: uppercase; letter-spacing: 2px; }
        .meta p { margin: 0 0 2px; }
        .terms { width: 100%; margin: 2px 0 10px; border-collapse: collapse; }
        .terms td { padding: 2px 0; vertical-align: top; }
        .terms td.label { width: 170px; }
        .terms td.value { font-weight: bold; }
        .signatures { width: 100%; margin-top: 18px; page-break-inside: avoid; }
        .signatures td { width: 50%; vertical-align: top; padding: 0 24px 0 0; }
        .line { border-bottom: 1px solid #000; height: 54px; margin: 4px 0; }
    </style>
</head>
<body>
    <table class="header" width="100%">
        <tr>
            @if ($logo)
                <td width="70"><img src="{{ $logo }}" style="height: 48px; max-width: 120px;" alt=""></td>
            @endif
            <td>
                <div class="company">{{ $letter['company'] }}</div>
                <div class="title">{{ $letter['title'] }}</div>
            </td>
        </tr>
    </table>

    <div class="meta" style="margin-bottom: 10px;">
        @if (! empty($letter['number']))
            <p>Nomor: {{ $letter['number'] }}</p>
        @endif
        <p>{{ $letter['date'] }}</p>
        <p>Perihal: <strong>{{ $letter['subject'] }}</strong></p>
    </div>

    <p style="margin-bottom: 10px;">Kepada Yth.<br><strong>{{ $letter['candidate'] }}</strong></p>

    @foreach ($letter['opening'] as $paragraph)
        <p>{!! nl2br(e($paragraph)) !!}</p>
    @endforeach

    <table class="terms">
        <tr><td class="label">Posisi</td><td class="value">: {{ $letter['position'] }}</td></tr>
        @if (! empty($letter['department']))
            <tr><td class="label">Departemen</td><td class="value">: {{ $letter['department'] }}</td></tr>
        @endif
        @if (! empty($letter['branch']))
            <tr><td class="label">Cabang</td><td class="value">: {{ $letter['branch'] }}</td></tr>
        @endif
        @if (! empty($letter['manager']))
            <tr><td class="label">Atasan</td><td class="value">: {{ $letter['manager'] }}</td></tr>
        @endif
        @if (! empty($letter['contract']))
            <tr><td class="label">Status kerja</td><td class="value">: {{ $letter['contract'] }}</td></tr>
        @endif
        <tr><td class="label">Tanggal mulai kerja</td><td class="value">: {{ $letter['startOn'] ?? '—' }}</td></tr>
        <tr><td class="label">Masa percobaan</td><td class="value">: {{ $letter['probation'] ?? '—' }}</td></tr>
        <tr><td class="label">Gaji pokok per bulan</td><td class="value">: {{ $letter['salary'] ?? '—' }}</td></tr>
    </table>

    @if (! empty($letter['note']))
        <p><strong>Catatan:</strong> {!! nl2br(e($letter['note'])) !!}</p>
    @endif

    @foreach ($letter['closing'] as $paragraph)
        <p>{!! nl2br(e($paragraph)) !!}</p>
    @endforeach

    <table class="signatures">
        <tr>
            <td>
                <p>Hormat kami,<br><strong>{{ $letter['company'] }}</strong></p>
                <div class="line"></div>
                @if (! empty($letter['signer']['name']))
                    <p style="margin: 0;"><strong>{{ $letter['signer']['name'] }}</strong></p>
                @endif
                <p style="margin: 0;">{{ $letter['signer']['title'] }}</p>
            </td>
            <td>
                <p>Setuju dan menerima,<br><strong>Tanggal: ____________________</strong></p>
                <div class="line"></div>
                <p style="margin: 0;">{{ $letter['candidate'] }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
