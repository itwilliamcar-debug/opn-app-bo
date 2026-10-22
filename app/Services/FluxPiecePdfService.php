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
        $dompdf->setCallbacks([
            [
                'event' => 'end_document',
                'f' => function (int $pageNumber, int $pageCount, $canvas, $fontMetrics) {
                    $font = $fontMetrics->getFont('DejaVu Sans', 'bold');
                    $canvas->set_opacity(0.22);
                    $largeur = $canvas->get_width();
                    $hauteur = $canvas->get_height();
                    for ($y = 36; $y < $hauteur + 20; $y += 148) {
                        for ($x = -24; $x < $largeur; $x += 188) {
                            $canvas->text($x, $y, 'OPN', $font, 32, [0.055, 0.165, 0.310], 0, 1.2, -32);
                        }
                    }
                },
            ],
        ]);
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
