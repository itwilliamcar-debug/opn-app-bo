<?php

namespace App\Services;

use App\Models\FluxPieceCommerciale;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

class FluxPiecePdfService
{
    public function telecharger(FluxPieceCommerciale $piece): Response
    {
        $piece->loadMissing(['lignes', 'antenne']);

        $titre = $piece->type === FluxPieceCommerciale::TYPE_FACTURE
            ? 'Facture OPN'
            : 'Bon de commande OPN';

        $safeNumero = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($piece->numero ?: 'DOC')) ?: 'DOC';
        $filename = $safeNumero . '.pdf';

        $html = View::make('pdf.flux-piece', [
            'piece' => $piece,
            'titre' => $titre,
            'lignes' => $piece->lignes ?? collect(),
        ])->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot(base_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
