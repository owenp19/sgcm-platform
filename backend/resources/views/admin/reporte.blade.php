<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8px; color: #071a2f; }
        h1 { text-align: center; font-size: 16px; color: #1d72b5; margin-top: 10px; }
        .generated { text-align: center; font-size: 9px; margin-top: 6px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1d72b5; color: #ffffff; font-size: 8px; text-align: left; padding: 5px 6px; }
        td { border-bottom: 1px solid #e0e4e8; padding: 4px 6px; font-size: 7.5px; word-wrap: break-word; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>Reporte SGCM - San Andrés Islas</h1>
    <p class="generated">Generado: {{ $generatedAt->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm') }}</p>

    <table>
        <thead>
            <tr>
                <th>Radicado</th>
                <th>Trámite</th>
                <th>Ciudadano</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td>{{ $r['tracking_number'] }}</td>
                    <td>{{ $r['procedure_name'] }}</td>
                    <td>{{ $r['citizen_name'] }}</td>
                    <td>{{ $r['status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Sin registros</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
