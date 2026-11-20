<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SV DTE Transmission Sandbox</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 24px 16px; }
        .wrap { max-width: 1100px; margin: 0 auto; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 18px; margin-bottom: 16px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        label { display: grid; gap: 6px; font-size: 0.9rem; color: #94a3b8; }
        input, select { padding: 8px 10px; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: #f8fafc; }
        button { padding: 9px 14px; border: none; border-radius: 6px; background: #38bdf8; color: #0f172a; font-weight: 600; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #334155; font-size: 0.85rem; }
        .alert { padding: 10px 12px; border-radius: 6px; background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>SV Electronic Invoicing (DTE) Transmission Sandbox</h1>
        <p>Independent Laravel 13 simulator for El Salvador DTE flows (FE 01, CCF 03, FSE 14), JWS envelope signing, contingency batching (v4), and invalidation events (v3) with synthetic data only.</p>
    </div>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif

    <div class="card">
        <h2>Emit Synthetic DTE</h2>
        <form method="POST" action="{{ route('dte.emit') }}" class="grid">
            @csrf
            <label>DTE Type
                <select name="tipo_dte">
                    <option value="01">01 - Factura (v2)</option>
                    <option value="03">03 - Credito Fiscal (v4)</option>
                    <option value="14">14 - Sujeto Excluido (v2)</option>
                </select>
            </label>
            <label>Receiver Name <input name="receptor_nombre" value="Cliente Demo S.A." required></label>
            <label>Receiver Doc (DUI/NIT) <input name="receptor_doc" value="06140101901010" required></label>
            <label>Email <input name="receptor_correo" type="email" value="receptor@example.test" required></label>
            <label>Item Description <input name="descripcion" value="Servicio Consultoria Tech" required></label>
            <label>Quantity <input name="cantidad" type="number" step="1" value="1" required></label>
            <label>Unit Price (USD) <input name="precio_unitario" type="number" step="0.01" value="100.00" required></label>
            <label style="align-content:center;"><input type="checkbox" name="simulate_outage" value="1"> Simulate MH Outage (Contingency)</label>
            <div style="align-self:end;"><button type="submit">Sign & Transmit DTE</button></div>
        </form>
        <form method="POST" action="{{ route('dte.contingency') }}" style="margin-top:12px;">
            @csrf
            <button type="submit" style="background:#f59e0b;">Dispatch Pending Contingency Batch (v4)</button>
        </form>
    </div>

    <div class="card">
        <h2>DTE Documents</h2>
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Numero Control</th>
                    <th>Codigo Generacion</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Sello Recibido</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $doc)
                    <tr>
                        <td>{{ $doc->tipo_dte }} (v{{ $doc->schema_version }})</td>
                        <td>{{ $doc->numero_control }}</td>
                        <td>{{ $doc->codigo_generacion }}</td>
                        <td>{{ $doc->status }}</td>
                        <td>${{ number_format($doc->total_pagar, 2) }}</td>
                        <td>{{ $doc->sello_recibido ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No DTEs emitted yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
