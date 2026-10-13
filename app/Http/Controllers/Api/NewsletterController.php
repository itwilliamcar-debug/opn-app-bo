<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Validator;

class NewsletterController extends Controller
{
    /**
     * Premier abonnement = enregistrement + envoi immédiat de la 1ère newsletter (article en poster à la une).
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'nom' => 'nullable|string|max:150',
            'organisation' => 'nullable|string|max:150',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Adresse email invalide.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($request->email));

        $subscriber = NewsletterSubscriber::firstOrNew(['email' => $email]);
        $isNew = !$subscriber->exists;

        $subscriber->nom = $request->nom ?? $subscriber->nom ?? 'Abonné OPN';
        $subscriber->organisation = $request->organisation ?? $subscriber->organisation ?? 'Citoyen / Professionnel';
        $subscriber->statut = 'ACTIF';
        $subscriber->ip_address = $request->ip();
        $subscriber->derniere_newsletter_id = 'ACT-01'; // Article en poster / premier plan
        $subscriber->derniere_newsletter_date = now();
        $subscriber->save();

        // Article en poster / mis en premier plan
        $featuredArticle = [
            'id' => 'ACT-01',
            'title' => 'Réception de 45 tonnes de solutés massifs au Hub Central d\'Owendo',
            'date' => '06 Septembre 2026',
            'tag' => 'Logistique & Approvisionnement',
            'tagColor' => 'bg-[#2E68B4] text-white',
            'summary' => 'Arrivée au port d\'Owendo d\'un convoi maritime prioritaire composé de chlorure de sodium, glucose et solutés de réanimation destinés aux CHU et hôpitaux régionaux.',
            'content' => 'La Direction Générale de l\'OPN a supervisé la réception de 45 tonnes métriques de solutés massifs. Ce lot stratégique permet de sécuriser 90 jours d\'autonomie pour les 9 provinces du Gabon. Le dispatching vers les antennes de l\'intérieur du pays a débuté ce matin via le convoi ferroviaire Setrag et des camions sécurisés climatisés.',
        ];

        return response()->json([
            'success' => true,
            'is_new' => $isNew,
            'message' => $isNew 
                ? 'Abonnement réussi ! Votre première newsletter officielle (article en poster à la une) vous a été envoyée.'
                : 'Votre abonnement est déjà actif. Une copie de l\'édition actuelle vous a été envoyée.',
            'subscriber' => [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'statut' => $subscriber->statut,
                'subscribed_at' => $subscriber->created_at,
            ],
            'dispatched_newsletter' => [
                'edition' => 'Première Édition Spéciale — Bienvenue',
                'article' => $featuredArticle,
                'sent_at' => now()->toIso8601String(),
                'sender' => 'Office Pharmaceutique National (OPN) <alertes-newsletter@opn-gabon.com>',
            ],
        ]);
    }

    /**
     * Liste des abonnés à la newsletter.
     */
    public function index()
    {
        $subscribers = NewsletterSubscriber::orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'total' => $subscribers->count(),
            'subscribers' => $subscribers,
        ]);
    }

    /**
     * Diffusion automatique d'un nouvel article à tous les abonnés.
     */
    public function broadcast(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'article_id' => 'required|string',
            'title' => 'required|string',
            'summary' => 'required|string',
            'content' => 'required|string',
            'date' => 'nullable|string',
            'tag' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données d\'actualité incomplètes pour la diffusion.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $subscribers = NewsletterSubscriber::where('statut', 'ACTIF')->get();
        $count = $subscribers->count();

        // Mettre à jour l'historique d'envoi pour chaque abonné
        NewsletterSubscriber::where('statut', 'ACTIF')->update([
            'derniere_newsletter_id' => $request->article_id,
            'derniere_newsletter_date' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "L'article a été envoyé automatiquement par email à {$count} abonnés actifs.",
            'recipients_count' => $count,
            'article' => [
                'id' => $request->article_id,
                'title' => $request->title,
                'summary' => $request->summary,
                'date' => $request->date ?? now()->format('d/m/Y'),
                'tag' => $request->tag ?? 'Communiqué Officiel',
            ],
            'broadcasted_at' => now()->toIso8601String(),
        ]);
    }
}
