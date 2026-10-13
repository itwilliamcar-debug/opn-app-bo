<?php

namespace App\Services;

/**
 * Seuils de visas institutionnels OPN (XAF).
 * ≤ 5M : pas de visa DIR/DAF/DG
 * 5M–20M : DIR → DAF
 * > 20M : DIR → DAF → DG
 */
class VisaCircuitService
{
    public const SANS_VISA_MAX = 5_000_000;
    public const DIR_DAF_MAX = 20_000_000;

    public function resolve(?float $montantXaf): array
    {
        $montant = (float) ($montantXaf ?? 0);

        if ($montant <= self::SANS_VISA_MAX) {
            return [
                'code' => 'SANS_VISA',
                'steps' => [],
                'label' => '≤ 5 000 000 XAF — sans visa DIR / DAF / DG',
                'next_statut_after_appro' => 'VALIDEE_NATIONALE',
                'requires_dir' => false,
                'requires_daf' => false,
                'requires_dg' => false,
            ];
        }

        if ($montant <= self::DIR_DAF_MAX) {
            return [
                'code' => 'DIR_DAF',
                'steps' => ['DIR', 'DAF'],
                'label' => '5–20 M XAF — visas DIR puis DAF',
                'next_statut_after_appro' => 'EN_VISA_DIR',
                'requires_dir' => true,
                'requires_daf' => true,
                'requires_dg' => false,
            ];
        }

        return [
            'code' => 'DIR_DAF_DG',
            'steps' => ['DIR', 'DAF', 'DG'],
            'label' => '> 20 000 000 XAF — visas DIR → DAF → DG',
            'next_statut_after_appro' => 'EN_VISA_DIR',
            'requires_dir' => true,
            'requires_daf' => true,
            'requires_dg' => true,
        ];
    }

    public function nextAfterVisa(string $currentStatut, array $circuit): string
    {
        if ($currentStatut === 'EN_VISA_DIR') {
            return $circuit['requires_daf'] ? 'EN_VISA_DAF' : 'APPROUVEE_CIRCUIT';
        }
        if ($currentStatut === 'EN_VISA_DAF') {
            return $circuit['requires_dg'] ? 'EN_VISA_DG' : 'APPROUVEE_CIRCUIT';
        }
        if ($currentStatut === 'EN_VISA_DG') {
            return 'APPROUVEE_CIRCUIT';
        }

        return $currentStatut;
    }

    public function expectedRoleForStatut(string $statut): ?string
    {
        return match ($statut) {
            'EN_VISA_DIR' => 'DIR',
            'EN_VISA_DAF' => 'DAF',
            'EN_VISA_DG' => 'DG',
            default => null,
        };
    }
}
