<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; color: #071a2f; }
        .top-bar { background: #1d72b5; height: 8px; width: 100%; }
        .header { padding: 22px 50px 0; position: relative; }
        .brand { font-size: 22px; font-weight: bold; }
        .brand-sub { font-size: 10px; color: #5c6b7a; margin-top: 3px; }
        .seal { position: absolute; top: 22px; right: 50px; text-align: right; width: 110px; }
        .seal-label { font-size: 8px; color: #5c6b7a; }
        .seal-code { font-size: 7px; color: #9aa3ae; margin-top: 4px; }
        hr.sep { border: none; border-top: 1px solid #e0e4e8; margin: 14px 50px 0; }
        h1.title { text-align: center; color: #1d72b5; font-size: 16px; margin-top: 18px; }
        .certifies { text-align: center; font-size: 10px; margin-top: 20px; }
        .citizen { text-align: center; font-size: 13px; font-weight: bold; margin-top: 12px; }
        .doc-line { text-align: center; font-size: 10px; margin-top: 10px; }
        .orange-line { border: none; border-top: 1px solid #f39218; margin: 16px 80px 0; }
        .details { margin: 24px 70px 0; font-size: 9px; }
        .details table { width: 100%; border-collapse: collapse; }
        .details td { padding: 4px 0; vertical-align: top; }
        .label { color: #1d72b5; font-weight: bold; width: 110px; }
        .status-approved { color: #2e7d32; }
        hr.sep2 { border: none; border-top: 1px solid #e0e4e8; margin: 26px 50px 0; }
        .qr-wrap { text-align: center; margin-top: 24px; }
        .qr-wrap img { width: 100px; height: 100px; }
        .qr-note { text-align: center; font-size: 7px; color: #9aa3ae; margin-top: 8px; }
        .footer { background: #071a2f; color: #ffffff; font-size: 7px; text-align: center;
                  padding: 8px 0; position: fixed; bottom: 0; left: 0; width: 100%; }
    </style>
</head>
<body>
    <div class="top-bar"></div>

    <div class="header">
        <div class="brand">SGCM</div>
        <div class="brand-sub">Sistema de Gestión y Control Migratorio Raizal<br>San Andrés, Providencia y Santa Catalina</div>
        <div class="seal">
            <div class="seal-label">Código de validación</div>
            <div class="seal-code">{{ $request->tracking_number }}</div>
        </div>
    </div>

    <hr class="sep">

    <h1 class="title">CONSTANCIA DE TRÁMITE</h1>
    <p class="certifies">La SGCM certifica que:</p>
    <p class="citizen">{{ $request->first_name }} {{ $request->last_name }}</p>
    <p class="doc-line">{{ $request->document_type ?: 'Documento' }}: {{ $request->document_number ?: 'N/A' }}</p>

    <hr class="orange-line">

    <div class="details">
        <table>
            <tr>
                <td class="label">Trámite:</td>
                <td>{{ $request->procedure_name }}</td>
                <td class="label" style="width:130px;">Radicado:</td>
                <td>{{ $request->tracking_number }}</td>
            </tr>
            <tr>
                <td class="label">Estado:</td>
                <td class="{{ $request->status === 'aprobado' || $request->status === 'finalizado' ? 'status-approved' : '' }}">{{ $statusLabel }}</td>
                <td class="label">Fecha de radicación:</td>
                <td>{{ optional($request->submitted_at)->format('d/m/Y') }}</td>
            </tr>
            @if($request->resolved_at)
                <tr>
                    <td class="label">Fecha de resolución:</td>
                    <td>{{ $request->resolved_at->format('d/m/Y') }}</td>
                    @if($request->assigned_name)
                        <td class="label">Funcionario:</td>
                        <td>{{ $request->assigned_name }}</td>
                    @endif
                </tr>
            @elseif($request->assigned_name)
                <tr>
                    <td class="label">Funcionario:</td>
                    <td>{{ $request->assigned_name }}</td>
                </tr>
            @endif
        </table>
    </div>

    <hr class="sep2">

    <div class="qr-wrap">
        <img src="{{ $qrDataUri }}" alt="QR">
        <div class="qr-note">Escanee el código QR para validar la autenticidad de este documento</div>
        <div class="qr-note">Emitido el {{ $issuedAt->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm') }}</div>
    </div>

    <div class="footer">SGCM San Andrés Islas — occre@sanandres.gov.co</div>
</body>
</html>
