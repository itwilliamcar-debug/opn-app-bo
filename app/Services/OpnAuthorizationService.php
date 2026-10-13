<?php

namespace App\Services;

use App\Models\User;
use App\Models\AntenneProvinciale;

class OpnAuthorizationService
{
    /**
     * Matrice RBAC OPN — SUPER_ADMIN ≠ Directeur Appro (APPRO_NATIONAL)
     * Circuit : Demandeur → Chef Antenne → Collaborateur Appro → APPRO_NATIONAL → Achats/DIR/DAF/DG
     */
    public const ROLES_PERMISSIONS = [
        'SUPER_ADMIN' => [
            'scope' => 'GLOBAL',
            'permissions' => [
                'admin.users', 'admin.settings', 'admin.antennes', 'admin.structures',
                'user.create', 'user.update', 'user.disable', 'user.view',
                'audit.view', 'audit.trail.view', 'audit.export', 'system.logs',
                'demand.supervise.readonly', 'supply.supervise.readonly',
                'stock.view.national', 'dashboard.national.view',
                'helpdesk.view', 'session.view',
            ],
        ],
        'ADMIN_IT' => [
            'scope' => 'SERVICE',
            'permissions' => [
                'user.create', 'user.update', 'user.disable', 'user.view',
                'helpdesk.view', 'helpdesk.manage', 'session.view', 'system.logs',
            ],
        ],
        'DG' => [
            'scope' => 'NATIONAL',
            'permissions' => [
                'dashboard.national.view', 'demand.view.national', 'order.view.national',
                'demand.visa.dg', 'order.approve.dg', 'order.reject.dg', 'procurement.view',
                'stock.view.national', 'emergency.view.national', 'report.view.national',
                'report.sign.dg', 'audit.view',
                'document.view', 'document.download',
                'suggestion.view', 'suggestion.decide',
            ],
        ],
        'DIR' => [
            'scope' => 'NATIONAL',
            'permissions' => [
                'dashboard.national.view', 'demand.view.national', 'demand.visa.dir',
                'demand.comment', 'order.view.national', 'stock.view.national',
                'procurement.view', 'logistics.view.national', 'report.view.national',
            ],
        ],
        'DAF' => [
            'scope' => 'NATIONAL',
            'permissions' => [
                'dashboard.national.view',
                'finance.view', 'budget.control', 'demand.visa.daf',
                'order.validate.financial', 'order.reject.financial', 'supplier.view',
                'report.finance.view', 'report.finance.sign', 'demand.view.national',
                'procurement.view', 'document.view', 'document.download',
            ],
        ],
        'APPRO_NATIONAL' => [
            'scope' => 'NATIONAL',
            'permissions' => [
                'dashboard.national.view', 'supply.consolidate', 'cmm.calculate',
                'demand.view.national', 'demand.instruct', 'demand.validate.national',
                'demand.reject', 'demand.transfer.achats', 'demand.comment',
                'order.create', 'stock.view.national', 'emergency.view.supply',
                'delivery.close.national', 'report.supply.view',
                'governance.view', 'governance.task.assign', 'governance.task.execute',
                'governance.task.submit', 'governance.review.decide',
                'governance.correction.submit',
                'document.view', 'document.upload', 'document.download', 'document.replace', 'document.archive',
                'suggestion.view', 'suggestion.create', 'suggestion.submit',
            ],
        ],
        'COLLABORATEUR_APPRO' => [
            'scope' => 'NATIONAL',
            'permissions' => [
                'dashboard.national.view', 'demand.view.national', 'demand.instruct',
                'demand.comment', 'demand.adjust.qty', 'demand.reject',
                'cmm.view', 'supply.view', 'stock.view.national', 'report.supply.view',
                'emergency.view.supply',
                'governance.view', 'governance.task.execute', 'governance.task.submit',
                'governance.correction.submit',
                'document.view', 'document.upload', 'document.download', 'document.replace',
                'suggestion.view', 'suggestion.create', 'suggestion.submit',
            ],
        ],
        'ACHATS' => [
            'scope' => 'NATIONAL',
            'permissions' => [
                'procurement.manage', 'supplier.manage', 'supplier.view',
                'po.create', 'po.update', 'quotation.compare', 'order.view.national',
                'demand.view.national', 'report.procurement.view',
            ],
        ],
        'PHARMACIEN_CHEF_NATIONAL' => [
            'scope' => 'NATIONAL',
            'permissions' => [
                'pharmacy.catalog.manage', 'dci.manage', 'fefo.supervise',
                'stock.view.national', 'lots.view.national', 'quality.control',
                'report.pharmacy.view',
            ],
        ],
        'PHARMACIEN_CHEF_ANTENNE' => [
            'scope' => 'ANTENNA',
            'permissions' => [
                'pharmacy.local.view', 'stock.view.antenna', 'lots.view.antenna',
                'demand.view.antenna', 'demand.review.pharmacy', 'demand.comment',
                'alert.create.antenna',
            ],
        ],
        'CHEF_ANTENNE_PROVINCIALE' => [
            'scope' => 'PROVINCE',
            'permissions' => [
                'antenna.dashboard.view', 'structures.view.antenna', 'stock.view.antenna',
                'demand.view.antenna', 'demand.create.antenna', 'demand.validate.partial',
                'demand.validate.total', 'demand.reject.antenna', 'demand.sign.antenna',
                'delivery.view.antenna', 'emergency.view.antenna', 'emergency.create.antenna',
                'report.antenna.view',
            ],
        ],
        'COORDINATEUR_URGENCES' => [
            'scope' => 'NATIONAL',
            'permissions' => [
                'emergency.view.all', 'emergency.create', 'emergency.manage',
                'crisis.deploy', 'team.assign', 'emergency.resolve',
                'stock.view.urgences', 'report.emergency.view',
            ],
        ],
        'GESTIONNAIRE_LOGISTIQUE' => [
            'scope' => 'NATIONAL',
            'permissions' => [
                'logistics.dashboard.view', 'mission.create', 'mission.assign',
                'mission.view.all', 'fleet.manage', 'delivery.view.all',
                'delivery.assign', 'report.mission.validate', 'delivery.close',
                'emergency.view.logistics',
            ],
        ],
        'CHEF_CONVOI_SECURITE' => [
            'scope' => 'ASSIGNED_MISSIONS',
            'permissions' => [
                'mission.view.assigned', 'convoy.status.update', 'incident.report',
                'checkpoint.validate', 'report.mission.sign',
            ],
        ],
        'CONVOYEUR' => [
            'scope' => 'ASSIGNED_MISSIONS',
            'permissions' => [
                'mission.view.assigned', 'mission.start', 'loading.confirm',
                'incident.report', 'qr.scan', 'delivery.receive',
                'delivery.signoff', 'report.mission.submit',
            ],
        ],
        'DEMANDEUR_PHARMACIEN' => [
            'scope' => 'STRUCTURE',
            'permissions' => [
                'demand.own.view', 'demand.own.create', 'order.own.view',
                'delivery.own.view', 'delivery.receive.local', 'helpdesk.create',
            ],
        ],
        'DEMANDEUR_STRUCTURE' => [
            'scope' => 'STRUCTURE',
            'permissions' => [
                'demand.structure.view', 'demand.structure.create', 'order.structure.view',
                'delivery.structure.view', 'delivery.receive.local', 'helpdesk.create',
            ],
        ],
        'COMMUNICATION' => [
            'scope' => 'NATIONAL',
            'permissions' => [
                'portal.cms.manage', 'tender.publish', 'press.release',
                'campaign.manage', 'report.communication.view',
            ],
        ],
        'AUDITEUR_INTERNE' => [
            'scope' => 'GLOBAL',
            'permissions' => [
                'audit.view', 'audit.trail.view', 'audit.export', 'timeline.inspect',
                'compliance.report', 'stock.view.national', 'demand.view.national',
                'demand.supervise.readonly', 'supply.supervise.readonly',
                'dashboard.national.view',
            ],
        ],
    ];

    public function can(User $user, string $permission, ?int $targetAntenneId = null, ?int $targetStructureId = null): bool
    {
        $codeRole = $user->code_role ?? 'DEMANDEUR_STRUCTURE';
        $roleConfig = self::ROLES_PERMISSIONS[$codeRole] ?? null;

        if (!$roleConfig) {
            return false;
        }

        $permissions = $roleConfig['permissions'];

        $allowed = in_array($permission, $permissions, true)
            || in_array('*', $permissions, true)
            || ($permission === 'demand.view.antenna' && in_array('demand.view.national', $permissions, true))
            || ($permission === 'demand.view.national' && in_array('demand.supervise.readonly', $permissions, true));

        if (!$allowed) {
            return false;
        }

        $scope = $roleConfig['scope'];

        if ($scope === 'GLOBAL' || $scope === 'NATIONAL' || $scope === 'SERVICE') {
            return true;
        }

        if ($scope === 'ANTENNA' || $scope === 'PROVINCE') {
            if ($targetAntenneId !== null && (int) $user->province_id !== (int) $targetAntenneId) {
                return false;
            }
            return true;
        }

        if ($scope === 'STRUCTURE') {
            if ($targetStructureId !== null && (int) $user->structure_id !== (int) $targetStructureId) {
                return false;
            }
            return true;
        }

        return true;
    }

    public function applyScope($query, User $user, string $antenneColumn = 'antenne_id')
    {
        $codeRole = $user->code_role ?? 'DEMANDEUR_STRUCTURE';
        $roleConfig = self::ROLES_PERMISSIONS[$codeRole] ?? null;
        $scope = $roleConfig['scope'] ?? 'PROVINCE';

        if ($codeRole === 'SUPER_ADMIN' || $scope === 'GLOBAL' || $scope === 'NATIONAL' || $scope === 'SERVICE') {
            return $query;
        }

        if ($scope === 'ANTENNA' || $scope === 'PROVINCE') {
            if ($user->province_id) {
                return $query->where($antenneColumn, $user->province_id);
            }
        }

        if ($scope === 'STRUCTURE' && $user->structure_id) {
            $model = $query->getModel();
            // structures_sanitaires : la PK est `id` (pas de colonne structure_id)
            if ($model instanceof \App\Models\StructureSanitaire) {
                return $query->where($model->getQualifiedKeyName(), $user->structure_id);
            }

            return $query->where('structure_id', $user->structure_id);
        }

        // STRUCTURE sans structure_id : restreindre à l'antenne du user si connue
        if ($scope === 'STRUCTURE' && $user->province_id) {
            return $query->where($antenneColumn, $user->province_id);
        }

        return $query;
    }

    public function getEnforcedItDestination(User $user): array
    {
        $antenne = AntenneProvinciale::find($user->province_id);

        $code = $antenne ? $antenne->code_antenne : 'EST';
        $nom = $antenne ? $antenne->nom : 'Estuaire (Libreville/Owendo)';
        $chefLieu = $antenne ? $antenne->chef_lieu : 'Libreville';

        $itKey = match ($code) {
            'EST' => 'IT_LIBREVILLE_OWENDO',
            'G2-FRV' => 'IT_FRANCEVILLE',
            'G3-LAM' => 'IT_LAMBARENE',
            'G4-MOU' => 'IT_MOUILA',
            'G5-TCH' => 'IT_TCHIBANGA',
            'G6-MAK' => 'IT_MAKOKOU',
            'G7-KLM' => 'IT_KOULAMOUTOU',
            'G8-POG' => 'IT_PORT_GENTIL',
            'G9-OYE' => 'IT_OYEM',
            default => 'IT_LIBREVILLE_OWENDO',
        };

        return [
            'it_destination_code' => $itKey,
            'it_destination_label' => "Pôle IT {$chefLieu} ({$nom})",
            'antenne_id' => $user->province_id,
            'antenne_nom' => $nom,
            'chef_lieu' => $chefLieu,
            'is_enforced' => true,
        ];
    }
}
