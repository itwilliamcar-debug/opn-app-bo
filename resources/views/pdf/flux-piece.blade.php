<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $titre }} — {{ $piece->numero }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 0;
            position: relative;
        }
        .watermark {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1000;
        }
        .watermark-cell {
            text-align: center;
            vertical-align: middle;
            font-size: 110px;
            font-weight: 700;
            letter-spacing: 22px;
            color: #cbd5e1;
            opacity: 0.22;
        }
        .content { position: relative; z-index: 1; }
        .header {
            width: 100%;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header td { vertical-align: top; }
        .brand-title { font-size: 13px; font-weight: 700; color: #0f172a; margin: 0; }
        .brand-sub { font-size: 9px; color: #64748b; margin: 2px 0 0; }
        .doc-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; margin: 0; text-align: right; }
        .doc-numero { font-size: 14px; font-weight: 700; font-family: DejaVu Sans Mono, monospace; color: #0f172a; margin: 2px 0; text-align: right; }
        .doc-date { font-size: 9px; color: #64748b; margin: 0; text-align: right; }
        .meta-table { width: 100%; margin-bottom: 12px; border-collapse: separate; border-spacing: 8px 0; }
        .meta-box {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 10px 12px;
            border-radius: 2px;
        }
        .meta-label { font-size: 8px; font-weight: 700; text-transform: uppercase; color: #64748b; margin: 0 0 4px; }
        .meta-value { font-size: 11px; font-weight: 700; color: #0f172a; margin: 0; }
        .meta-sub { font-size: 10px; color: #475569; margin: 2px 0 0; }
        .stats-table { width: 100%; margin-bottom: 14px; border-collapse: separate; border-spacing: 6px 0; }
        .stat-box {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            text-align: left;
        }
        .stat-label { font-size: 8px; text-transform: uppercase; color: #64748b; margin: 0 0 2px; }
        .stat-value { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; }
        table.lignes {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            border: 1px solid #e2e8f0;
        }
        table.lignes thead th {
            background: #f1f5f9;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            padding: 7px 8px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        table.lignes tbody td {
            padding: 7px 8px;
            border-top: 1px solid #f1f5f9;
            font-size: 10px;
            color: #1e293b;
        }
        table.lignes tfoot td {
            padding: 8px;
            border-top: 1px solid #cbd5e1;
            background: #f8fafc;
            font-weight: 700;
            font-size: 10px;
        }
        .text-right { text-align: right; }
        .note {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 10px 12px;
            font-size: 10px;
            color: #475569;
            margin-bottom: 18px;
        }
        .note strong { color: #0f172a; }
        .footer {
            width: 100%;
            border-top: 1px solid #cbd5e1;
            padding-top: 14px;
            margin-top: 8px;
        }
        .footer td { vertical-align: bottom; font-size: 9px; color: #64748b; }
        .visa { text-align: center; width: 160px; }
        .visa-line { border-top: 1px solid #94a3b8; margin-top: 42px; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="watermark">
        <table style="width:100%; height:100%;">
            <tr>
                <td class="watermark-cell">OPN</td>
            </tr>
        </table>
    </div>

    <div class="content">
        <table class="header">
            <tr>
                <td style="width: 58%;">
                    <p class="brand-title">Office Pharmaceutique National</p>
                    <p class="brand-sub">République Gabonaise · Chaîne d'approvisionnement</p>
                </td>
                <td style="width: 42%;">
                    <p class="doc-title">{{ $titre }}</p>
                    <p class="doc-numero">{{ $piece->numero }}</p>
                    <p class="doc-date">
                        {{ $piece->emitted_at ? $piece->emitted_at->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—' }}
                    </p>
                </td>
            </tr>
        </table>

        <table class="meta-table">
            <tr>
                <td style="width: 50%;">
                    <div class="meta-box">
                        <p class="meta-label">Demandeur</p>
                        <p class="meta-value">{{ $piece->demandeur_nom ?: '—' }}</p>
                        <p class="meta-sub">{{ $piece->partenaire_nom ?: '—' }}</p>
                    </div>
                </td>
                <td style="width: 50%;">
                    <div class="meta-box">
                        <p class="meta-label">Antenne</p>
                        <p class="meta-value">{{ $piece->antenne_nom ?: ($piece->antenne->nom ?? '—') }}</p>
                        <p class="meta-sub">Commande {{ $piece->numero_commande ?: '—' }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <table class="stats-table">
            <tr>
                <td style="width: 33%;">
                    <div class="stat-box">
                        <p class="stat-label">Pris en charge</p>
                        <p class="stat-value">{{ (int) ($piece->quantite_prise_totale ?? 0) }}</p>
                    </div>
                </td>
                <td style="width: 33%;">
                    <div class="stat-box">
                        <p class="stat-label">Reliquat</p>
                        <p class="stat-value">{{ (int) ($piece->quantite_reliquat_totale ?? 0) }}</p>
                    </div>
                </td>
                <td style="width: 34%;">
                    <div class="stat-box">
                        <p class="stat-label">Montant</p>
                        <p class="stat-value" style="font-size: 13px;">
                            {{ number_format((float) ($piece->montant_ttc_xaf ?? 0), 0, ',', ' ') }} XAF
                        </p>
                    </div>
                </td>
            </tr>
        </table>

        <table class="lignes">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th>Demandé</th>
                    <th>Pris</th>
                    <th>Reliquat</th>
                    <th>P.U.</th>
                    <th class="text-right">Montant</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lignes as $ligne)
                    <tr>
                        <td style="font-weight: 700;">{{ $ligne->designation }}</td>
                        <td>{{ (int) $ligne->quantite_demandee }}</td>
                        <td style="font-weight: 700;">{{ (int) $ligne->quantite_prise }}</td>
                        <td>{{ (int) $ligne->quantite_reliquat }}</td>
                        <td>{{ number_format((float) $ligne->prix_unitaire_xaf, 0, ',', ' ') }} XAF</td>
                        <td class="text-right" style="font-weight: 700;">
                            {{ number_format((float) $ligne->montant_ligne_xaf, 0, ',', ' ') }} XAF
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: #64748b; padding: 16px;">Aucune ligne</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right">Total TTC</td>
                    <td class="text-right">
                        {{ number_format((float) ($piece->montant_ttc_xaf ?? 0), 0, ',', ' ') }} XAF
                    </td>
                </tr>
            </tfoot>
        </table>

        @if (!empty($piece->note))
            <div class="note">
                <strong>Note :</strong> {{ $piece->note }}
            </div>
        @endif

        <table class="footer">
            <tr>
                <td>
                    Émis par {{ $piece->emitted_by_nom ?: 'Siège OPN' }}
                    · {{ $piece->emitted_at ? $piece->emitted_at->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—' }}
                </td>
                <td class="visa">
                    <div>Visa / Signature</div>
                    <div class="visa-line">Siège OPN</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
