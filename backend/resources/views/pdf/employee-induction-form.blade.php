<!DOCTYPE html>
<html lang="id">
@php
    /** A box with a tick when chosen, then its label. Written out as HTML because the PDF library has no form controls. */
    $box = fn (bool $on, string $label): string => '<span class="box'.($on ? ' on' : '').'">'.($on ? '&#10003;' : '&nbsp;').'</span> '.e($label);

    /** A row of options, each a box and a label. */
    $options = function (array $choices, ?string $extra = null) use ($box): string {
        $html = implode('<span class="gap"></span>', array_map(fn (array $choice): string => $box($choice[1], $choice[0]), $choices));

        return $html.($extra === null ? '' : '<span class="gap"></span><strong>'.e($extra).'</strong>');
    };

    $e = $form['employee'];
    $company = $form['company'];
@endphp
<head>
    <meta charset="utf-8">
    <title>Formulir data induk karyawan — {{ $e['name'] }}</title>
    <style>
        @page { margin: 1.1cm 1.3cm 1.2cm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; line-height: 1.25; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; padding: 0; }
        .bar { background: #d9d9d9; font-weight: bold; font-size: 8.5pt; padding: 3px 5px; margin: 6px 0 2px; page-break-after: avoid; }
        .head td { vertical-align: middle; }
        .title { background: #d9d9d9; text-align: center; font-size: 12pt; font-weight: bold; padding: 8px 4px; }
        .company { text-align: center; font-size: 11pt; font-weight: bold; padding: 5px 4px 1px; }
        .address { text-align: center; font-size: 7pt; padding: 0 4px 3px; }
        .side { border: 1px solid #999; padding: 3px 6px; }
        .side .k { font-weight: bold; font-size: 7.5pt; }
        .l { width: 21%; padding: 2px 0; }
        .c { width: 2%; padding: 2px 0; }
        .v { border-bottom: 1px solid #000; padding: 2px 3px; min-height: 12px; }
        .box { display: inline-block; width: 8px; height: 8px; line-height: 8px; border: 1px solid #000; text-align: center; font-size: 7pt; font-weight: bold; vertical-align: middle; }
        .gap { display: inline-block; width: 10px; }
        .pair td.l2 { width: 22%; padding: 3px 0; }
        .grid td { padding: 2px 3px; }
        .grid th { text-align: left; font-weight: bold; padding: 2px 3px; border-bottom: 1px solid #000; }
        .grid .line { border-bottom: 1px solid #000; height: 14px; }
        .branch { padding: 2px 0 3px; }
        .branch .no { font-size: 7pt; padding-left: 15px; }
        .note { font-size: 7pt; font-style: italic; margin: 1px 0 3px; }
        .consent { text-align: justify; font-size: 7.5pt; padding: 3px 0 5px; }
        .avoid { page-break-inside: avoid; }
        .sign { height: 50px; }
    </style>
</head>
<body>
    {{-- Header: logo, title and company, and the date and status of the file --}}
    <table class="head">
        <tr>
            <td style="width: 15%; text-align: center;">
                @if ($company['logo'])
                    <img src="{{ $company['logo'] }}" style="max-height: 62px; max-width: 90px;" alt="">
                @endif
            </td>
            <td style="width: 55%;">
                <div class="title">FORMULIR DATA INDUK KARYAWAN</div>
                <div class="company">{{ $company['name'] }}</div>
                @if ($company['address'])
                    <div class="address">{{ $company['address'] }}</div>
                @endif
            </td>
            <td style="width: 30%; padding-left: 8px;">
                <div class="side">
                    <div class="k">TANGGAL BERKAS</div>
                    <div>{{ $form['date'] }}</div>
                    <div class="k" style="margin-top: 4px;">STATUS BERKAS</div>
                    <div>{!! $box(true, 'KARYAWAN BARU') !!}</div>
                    <div>{!! $box(false, 'PERUBAHAN IDENTITAS') !!}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="bar">IDENTITAS PERUSAHAAN</div>
    <table>
        <tr><td class="l">NPWP 16</td><td class="c">:</td><td class="v">{{ $company['npwp16'] }}</td></tr>
    </table>
    <table>
        <tr>
            <td class="l">NITKU / NPWP 15</td><td class="c">:</td>
            <td>
                <table>
                    @foreach (array_chunk($form['branches'], 2) as $pair)
                        <tr>
                            @foreach ($pair as $branch)
                                <td class="branch" style="width: 50%;">
                                    <strong>{!! $box($branch['mine'], mb_strtoupper($branch['name'])) !!}</strong>
                                    <div class="no">{{ $branch['nitku'] }}@if ($branch['nitku'] && $branch['npwp15']) / @endif{{ $branch['npwp15'] }}</div>
                                </td>
                            @endforeach
                            @if (count($pair) === 1)<td></td>@endif
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
        <tr>
            <td class="l">Metode Perpajakan</td><td class="c">:</td>
            <td class="v" style="border-bottom: 0;">{!! $options($company['taxMethods']) !!}</td>
        </tr>
    </table>

    <div class="bar">INFORMASI KARYAWAN</div>
    <table class="pair">
        <tr>
            <td style="width: 52%;">
                <table>
                    <tr><td class="l" style="width: 36%;">No. Induk Karyawan</td><td class="c">:</td><td class="v">{{ $e['number'] }}</td></tr>
                    <tr><td class="l" style="width: 36%;">Status Karyawan</td><td class="c">:</td><td style="padding: 2px 3px;">{!! $options($e['status']) !!}</td></tr>
                    <tr><td class="l" style="width: 36%;">Tanggal Mulai Bekerja</td><td class="c">:</td><td class="v">{{ $e['joinedOn'] }}</td></tr>
                    <tr><td class="l" style="width: 36%;">Lokasi Bekerja</td><td class="c">:</td><td class="v">{{ $e['branch'] }}</td></tr>
                </table>
            </td>
            <td style="width: 3%;"></td>
            <td style="width: 45%;">
                <table>
                    <tr><td class="l2" style="width: 30%;">Divisi</td><td class="c">:</td><td class="v">{{ $e['division'] }}</td></tr>
                    <tr><td class="l2" style="width: 30%;">Jabatan</td><td class="c">:</td><td class="v">{{ $e['jobTitle'] }}</td></tr>
                    <tr><td class="l2" style="width: 30%;">Posisi</td><td class="c">:</td><td class="v">{{ $e['position'] }}</td></tr>
                    <tr><td class="l2" style="width: 30%;">Zona Absen</td><td class="c">:</td><td style="padding: 2px 3px;">{!! $options($e['zona']) !!}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="bar">INFORMASI PERINCIAN GAJI</div>
    <table>
        <tr>
            <td style="width: 55%;">
                <table>
                    <tr><td class="l" style="width: 34%;">Gaji Per Bulan &nbsp; Rp.</td><td class="c">:</td><td class="v">&nbsp;</td></tr>
                </table>
            </td>
            <td style="width: 3%;"></td>
            <td style="width: 42%;">
                <table class="grid" style="border: 1px solid #000;">
                    <tr><th colspan="3" style="text-align: center;">Perincian Gaji</th></tr>
                    <tr><td style="width: 55%;">Gaji Pokok</td><td style="width: 4%;">:</td><td class="line">&nbsp;</td></tr>
                    <tr><td>Tunjangan Jabatan</td><td>:</td><td class="line">&nbsp;</td></tr>
                    <tr><td>Tunjangan Masa Kerja</td><td>:</td><td class="line">&nbsp;</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @foreach ([
        ['BPJS Ketenagakerjaan', 'bpjsEmployment', $e['bpjsEmploymentCard']],
        ['BPJS Kesehatan', 'bpjsHealth', $e['bpjsHealthCard']],
    ] as [$heading, $key, $card])
        <div class="avoid" style="margin-top: 4px;">
            <strong>{{ $heading }}</strong>
            <table class="grid" style="margin-top: 1px;">
                <tr>
                    @foreach ($form['branches'] as $branch)
                        <td style="width: {{ floor(100 / max(count($form['branches']), 1)) }}%;">
                            {!! $box($branch['mine'], mb_strtoupper($branch['name'])) !!}
                            <div style="padding-left: 12px; font-weight: bold;">{{ $branch[$key] }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
            <table>
                <tr><td class="l" style="width: 21%;">Tanggal Daftar</td><td class="c">:</td><td class="v">&nbsp;</td></tr>
                <tr><td class="l" style="width: 21%;">No. Kartu</td><td class="c">:</td><td class="v">{{ $card }}</td></tr>
            </table>
        </div>
    @endforeach

    <div class="bar">IDENTITAS KARYAWAN</div>
    <table>
        <tr><td class="l">Nama Karyawan</td><td class="c">:</td><td class="v">{{ $e['name'] }}</td></tr>
        <tr><td class="l">Tempat Lahir</td><td class="c">:</td><td class="v">{{ $e['birthPlace'] }}</td></tr>
        <tr><td class="l">Tanggal Lahir</td><td class="c">:</td><td class="v">{{ $e['birthDate'] }}</td></tr>
        <tr><td class="l">Jenis Kelamin</td><td class="c">:</td><td style="padding: 2px 3px;">{!! $options($e['gender']) !!}</td></tr>
        <tr><td class="l">Agama</td><td class="c">:</td><td style="padding: 2px 3px;">{!! $options($e['religion']) !!}</td></tr>
        <tr><td class="l">Status Perkawinan</td><td class="c">:</td><td style="padding: 2px 3px;">{!! $options($e['marital'], $e['maritalOther']) !!}</td></tr>
        <tr><td class="l">Jumlah Tanggungan</td><td class="c">:</td><td class="v">{{ $e['dependants'] }}</td></tr>
        <tr><td class="l">Nama Ibu Kandung</td><td class="c">:</td><td class="v">{{ $e['motherName'] }}</td></tr>
        <tr><td class="l">Kewarganegaraan</td><td class="c">:</td><td style="padding: 2px 3px;">{!! $options([['WNI', false], ['WNA', false]]) !!}</td></tr>
        <tr><td class="l">Kartu Keluarga (KK)</td><td class="c">:</td><td class="v">{{ $e['familyCard'] }}</td></tr>
        <tr><td class="l">KTP / NPWP 16</td><td class="c">:</td><td class="v">{{ $e['idNumber'] }}</td></tr>
        <tr><td class="l">Paspor</td><td class="c">:</td><td class="v">&nbsp;</td></tr>
        <tr><td class="l">SIM A/B1/C</td><td class="c">:</td><td class="v">&nbsp;</td></tr>
    </table>

    <div class="bar avoid">INFORMASI KONTAK <span style="font-weight: normal;">(email aktif untuk keperluan slip gaji)</span></div>
    <table class="avoid">
        <tr><td class="l">No. Handphone</td><td class="c">:</td><td class="v">{{ $e['phone'] }}</td></tr>
        <tr><td class="l">Alamat Email</td><td class="c">:</td><td class="v">{{ $e['email'] }}</td></tr>
    </table>

    @foreach ([
        ['Alamat KTP', 'idAddress', 'idRtRw', 'idCity', 'idVillage', 'idProvince', 'idDistrict', 'idPostal', null],
        ['Alamat Tempat Tinggal', 'homeAddress', null, 'homeCity', 'homeVillage', 'homeProvince', 'homeDistrict', 'homePostal', 'Mohon dikosongkan apabila alamat sesuai dengan KTP'],
    ] as [$heading, $street, $rtrw, $city, $village, $province, $district, $postal, $hint])
        <div class="avoid" style="margin-top: 5px;">
            <table>
                <tr><td class="l">{{ $heading }}</td><td class="c">:</td><td class="v">{{ $e[$street] }}</td></tr>
            </table>
            @if ($hint)<div class="note" style="padding-left: 23%;">{{ $hint }}</div>@endif
            <table class="grid" style="margin-left: 23%; width: 77%;">
                <tr>
                    <td style="width: 16%;">RT/RW</td><td class="line" style="width: 30%; border-bottom: 1px solid #000;">{{ $rtrw ? $e[$rtrw] : '' }}</td>
                    <td style="width: 4%;"></td>
                    <td style="width: 15%;">Kab./Kota</td><td style="border-bottom: 1px solid #000;">{{ $e[$city] }}</td>
                </tr>
                <tr>
                    <td>Kelurahan</td><td style="border-bottom: 1px solid #000;">{{ $e[$village] }}</td><td></td>
                    <td>Provinsi</td><td style="border-bottom: 1px solid #000;">{{ $e[$province] }}</td>
                </tr>
                <tr>
                    <td>Kecamatan</td><td style="border-bottom: 1px solid #000;">{{ $e[$district] }}</td><td></td>
                    <td>Kode Pos</td><td style="border-bottom: 1px solid #000;">{{ $e[$postal] }}</td>
                </tr>
            </table>
        </div>
        @if ($loop->first)
            <table class="avoid" style="margin-top: 4px;">
                <tr><td class="l">Status Tempat Tinggal</td><td class="c">:</td><td style="padding: 2px 3px;">{!! $options([['MILIK SENDIRI', false], ['SEWA', false], ['MESS', false], ['ORANG TUA', false]]) !!}</td></tr>
            </table>
        @endif
    @endforeach

    <div class="bar avoid">REKENING BANK <span style="font-weight: normal;">(untuk sistem payroll)</span></div>
    <table class="avoid">
        <tr><td class="l">No. Rekening</td><td class="c">:</td><td class="v">{{ $e['bankNumber'] }}</td></tr>
        <tr><td class="l">Nama Pemilik Rekening</td><td class="c">:</td><td class="v">{{ $e['bankHolder'] }}</td></tr>
        <tr><td class="l">Nama Bank</td><td class="c">:</td><td class="v">{{ $e['bankName'] }}</td></tr>
        <tr><td class="l">Cabang Bank</td><td class="c">:</td><td class="v">{{ $e['bankBranch'] }}</td></tr>
    </table>

    <div class="bar avoid">KONTAK DARURAT</div>
    <table class="grid avoid">
        <tr><th style="width: 22%;">Hubungan Keluarga</th><th style="width: 50%;">Nama</th><th>No. Kontak HP</th></tr>
        <tr><td class="line">{{ $e['emergencyRelation'] }}</td><td class="line">{{ $e['emergencyName'] }}</td><td class="line">{{ $e['emergencyPhone'] }}</td></tr>
        <tr><td class="line">&nbsp;</td><td class="line">&nbsp;</td><td class="line">&nbsp;</td></tr>
    </table>

    <div class="bar avoid">RIWAYAT PENDIDIKAN</div>
    <table class="grid avoid">
        <tr><th style="width: 14%;">Status</th><th style="width: 46%;">Nama Sekolah atau Universitas</th><th style="width: 26%;">Jurusan</th><th>Tahun Lulus</th></tr>
        <tr><td class="line">{{ $e['education'] }}</td><td class="line">&nbsp;</td><td class="line">&nbsp;</td><td class="line">&nbsp;</td></tr>
        <tr><td class="line">&nbsp;</td><td class="line">&nbsp;</td><td class="line">&nbsp;</td><td class="line">&nbsp;</td></tr>
        <tr><td class="line">&nbsp;</td><td class="line">&nbsp;</td><td class="line">&nbsp;</td><td class="line">&nbsp;</td></tr>
    </table>

    <div class="avoid">
        <div class="bar">PERSETUJUAN</div>
        <div class="consent">Semua informasi dalam formulir ini adalah lengkap dan benar. Saya menyetujui bahwa data yang saya berikan menjadi milik perusahaan. Dengan menandatangani formulir ini, saya memberi kuasa kepada perusahaan untuk menerima semua kebenaran data dengan cara bagaimanapun dan menghubungi sumber manapun yang layak menurut perusahaan. Semua dokumen yang telah saya serahkan tidak akan dikembalikan. Apabila suatu hari terbukti ada ketidakbenaran dari data yang saya berikan, maka saya bersedia untuk diberhentikan tanpa minta ganti rugi apapun.</div>
        <table>
            <tr>
                <td style="width: 58%;">
                    <strong>Dokumen Wajib Dilengkapi</strong>
                    <div>1. Fotokopi Kartu Identitas (KTP/Passpor)</div>
                    <div>2. Fotokopi Kartu Keluarga</div>
                    <div>3. Fotokopi NPWP</div>
                    <div>4. Fotokopi Rekening Bank</div>
                    <div>5. Fotokopi BPJS Kesehatan</div>
                    <div>6. Fotokopi BPJS Ketenagakerjaan</div>
                    <div>7. Pasfoto ukuran 4 x 6</div>
                </td>
                <td style="width: 42%; text-align: center;">
                    <div>Tanggal: {{ $form['date'] }}</div>
                    <div class="sign"></div>
                    <div>({{ $e['name'] }})</div>
                    <div style="border-top: 1px solid #000; margin: 2px 10px 0; padding-top: 2px;">Tanda Tangan Karyawan</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
