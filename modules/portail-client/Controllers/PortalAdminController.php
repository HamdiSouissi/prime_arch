<?php
declare(strict_types=1);

namespace Modules\PortailClient\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\ViewContext;
use Modules\PortailClient\Models\PortailRepository;
use Modules\PortailClient\Services\PortailService;
use Throwable;

/**
 * Écran du cabinet : création, prolongation, révocation et journal des liens du portail.
 *
 * Le jeton en clair n’est jamais stocké ni journalisé : il n’est affiché qu’une fois, juste
 * après la création. Droits réutilisés : `clients.view` ou `projects.view` en lecture,
 * `clients.manage` ou `projects.manage` en écriture.
 */
final class PortalAdminController
{
    private PortailRepository $repo;
    private PortailService $service;

    public function __construct(?PortailRepository $repo = null, ?PortailService $service = null)
    {
        $this->repo = $repo ?? new PortailRepository();
        $this->service = $service ?? new PortailService();
    }

    /** Liste des liens, synthèse et journal d’un lien sélectionné. */
    public function index(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $today = date('Y-m-d');
        $stateRaw = (string) $request->query('state', '');
        $state = in_array($stateRaw, ['active', 'expired', 'revoked'], true) ? $stateRaw : '';
        $clientRaw = (string) $request->query('client', '');
        $filters = array_filter([
            'state' => $state,
            'client' => ctype_digit($clientRaw) ? $clientRaw : '',
        ], static fn (string $value): bool => $value !== '');

        $links = $this->repo->links($filters, $today);
        $all = $filters === [] ? $links : $this->repo->links([], $today);
        foreach ($links as $index => $link) {
            $links[$index]['state'] = PortailService::state($link, $today);
            $links[$index]['days_left'] = PortailService::daysLeft((string) $link['expires_on'], $today);
        }

        $selectedId = (int) $request->query('jeton', 0);
        $selected = $selectedId > 0 ? $this->repo->find($selectedId) : null;

        return Response::html(View::renderModule('portail-client', 'index', $this->context('portail.title') + [
            'today' => $today,
            'filters' => $filters,
            'links' => $links,
            'statistics' => PortailService::statistics($all, $today),
            'recentAccesses' => $this->repo->recentAccessCount(),
            'selected' => $selected,
            'accesses' => $selected === null ? [] : $this->repo->accesses((int) $selected['id']),
            'clients' => $this->repo->clientOptions(),
            'projects' => $this->repo->projectOptions(),
            'canManage' => $this->canManage(),
            'scopes' => PortailService::SCOPES,
            'defaultDays' => PortailService::DAYS_DEFAULT,
            'maxDays' => PortailService::DAYS_MAX,
            'notice' => Session::pullFlash('portail.notice'),
            'error' => Session::pullFlash('portail.error'),
            'newUrl' => Session::pullFlash('portail.new_url'),
            'newLabel' => Session::pullFlash('portail.new_label'),
            'errors' => Session::pullFlash('portail.errors', []),
            'values' => Session::pullFlash('portail.values', []),
        ]));
    }

    /** Crée un lien et affiche son adresse une seule fois. */
    public function store(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $clients = $this->repo->clientOptions();
        $projects = $this->repo->projectOptions();
        $clientIds = array_map(static fn (array $client): int => (int) $client['id'], $clients);
        $projectClients = [];
        foreach ($projects as $project) {
            $projectClients[(int) $project['id']] = (int) $project['client_id'];
        }
        $validated = $this->service->validateLink($request->all(), $clientIds, $projectClients);
        if ($validated['errors'] !== []) {
            Session::flash('portail.errors', $validated['errors']);
            Session::flash('portail.values', $request->all());

            return Response::redirect(url('/portail#portail-new'));
        }
        $today = date('Y-m-d');
        $expiresOn = PortailService::expiryDate((int) $validated['values']['expires_days'], $today);
        if ($expiresOn === null) {
            Session::flash('portail.error', __('portail.save_failed'));

            return Response::redirect(url('/portail'));
        }
        $values = $validated['values'];
        $values['expires_on'] = $expiresOn;
        $token = PortailService::newToken();

        try {
            $id = $this->repo->createLink(PortailService::hashToken($token), $values, Auth::id());
            (new AuditLogger())->record('portail.link_created', 'module', $id, [
                'client_id' => (int) $values['client_id'],
                'scope' => (string) $values['scope'],
                'expires_on' => $expiresOn,
            ], Auth::id());
            Session::flash('portail.notice', __('portail.link_created'));
            // Le jeton n’est affiché qu’une fois : il n’est ni stocké en clair ni relisible ensuite.
            Session::flash('portail.new_url', PortailService::linkUrl($token, Translator::locale()));
            Session::flash('portail.new_label', (string) $values['label']);
        } catch (Throwable $error) {
            error_log('Création d’un lien de portail impossible : ' . $error->getMessage());
            Session::flash('portail.error', __('portail.save_failed'));
            Session::flash('portail.values', $request->all());
        }

        return Response::redirect(url('/portail'));
    }

    /** Révoque un lien : l’accès est refusé dès la requête suivante. */
    public function revoke(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $row = $this->find($id);
        if ($row === null) {
            Session::flash('portail.error', __('portail.not_found'));

            return Response::redirect(url('/portail'));
        }
        if ($row['revoked_at'] !== null) {
            Session::flash('portail.error', __('portail.already_revoked'));

            return Response::redirect(url('/portail?jeton=' . (int) $row['id']));
        }
        $this->repo->revoke((int) $row['id']);
        (new AuditLogger())->record('portail.link_revoked', 'module', (int) $row['id'], [], Auth::id());
        Session::flash('portail.notice', __('portail.link_revoked'));

        return Response::redirect(url('/portail?jeton=' . (int) $row['id']));
    }

    /** Prolonge un lien non révoqué, dans la limite de 365 jours. */
    public function extend(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $row = $this->find($id);
        if ($row === null) {
            Session::flash('portail.error', __('portail.not_found'));

            return Response::redirect(url('/portail'));
        }
        if ($row['revoked_at'] !== null) {
            Session::flash('portail.error', __('portail.extend_revoked'));

            return Response::redirect(url('/portail?jeton=' . (int) $row['id']));
        }
        $today = date('Y-m-d');
        $days = PortailService::days($request->input('expires_days', ''));
        if ($days === null) {
            Session::flash('portail.error', __('portail.invalid_days'));

            return Response::redirect(url('/portail?jeton=' . (int) $row['id']));
        }
        // On prolonge à partir de la date la plus tardive : jamais de raccourcissement.
        $base = max($today, (string) $row['expires_on']);
        $expiresOn = PortailService::expiryDate($days, $base);
        $cap = PortailService::expiryDate(PortailService::DAYS_MAX, $today);
        if ($expiresOn === null || $cap === null) {
            Session::flash('portail.error', __('portail.save_failed'));

            return Response::redirect(url('/portail'));
        }
        if ($expiresOn > $cap) {
            $expiresOn = $cap;
        }
        $this->repo->extend((int) $row['id'], $expiresOn);
        (new AuditLogger())->record('portail.link_extended', 'module', (int) $row['id'], [
            'expires_on' => $expiresOn,
        ], Auth::id());
        Session::flash('portail.notice', __('portail.link_extended', ['date' => self::frenchDate($expiresOn)]));

        return Response::redirect(url('/portail?jeton=' . (int) $row['id']));
    }

    /** Supprime définitivement un lien et son journal (cascade). */
    public function destroy(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $row = $this->find($id);
        if ($row === null) {
            Session::flash('portail.error', __('portail.not_found'));

            return Response::redirect(url('/portail'));
        }
        $this->repo->deleteLink((int) $row['id']);
        (new AuditLogger())->record('portail.link_deleted', 'module', (int) $row['id'], [], Auth::id());
        Session::flash('portail.notice', __('portail.link_deleted'));

        return Response::redirect(url('/portail'));
    }

    private static function frenchDate(string $date): string
    {
        $timestamp = strtotime($date);

        return $timestamp === false ? $date : date('d/m/Y', $timestamp);
    }

    /** @return array<string,mixed>|null */
    private function find(string $id): ?array
    {
        if (!ctype_digit($id) || (int) $id < 1) {
            return null;
        }

        return $this->repo->find((int) $id);
    }

    /** @return array<string,mixed> */
    private function context(string $titleKey): array
    {
        return (new ViewContext())->authenticated(__($titleKey), 'portail-client');
    }

    private function requireView(): ?Response
    {
        return Auth::can('clients.view') || Auth::can('projects.view')
            ? null
            : Response::html(__('common.forbidden'), 403);
    }

    private function requireManage(): ?Response
    {
        return $this->canManage() ? null : Response::html(__('common.forbidden'), 403);
    }

    private function canManage(): bool
    {
        return Auth::can('clients.manage') || Auth::can('projects.manage');
    }
}
