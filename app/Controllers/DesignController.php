<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Écrans de démonstration du système de design, accessibles uniquement hors production.
 * Les données affichées sont fictives et ne sont jamais enregistrées.
 */
final class DesignController
{
    /** @var array<int,array{label:string,url:string,icon:string,key:string}> */
    private const NAVIGATION = [
        ['label' => 'Tableau de bord', 'short' => 'Accueil', 'url' => '/design/dashboard', 'icon' => 'grid', 'key' => 'dashboard'],
        ['label' => 'Clients', 'short' => 'Clients', 'url' => '/design/clients', 'icon' => 'people', 'key' => 'clients'],
        ['label' => 'Projets', 'short' => 'Projets', 'url' => '/design/projects', 'icon' => 'briefcase', 'key' => 'projects'],
        ['label' => 'Documents', 'short' => 'Docs', 'url' => '/design/documents', 'icon' => 'file-text', 'key' => 'documents'],
        ['label' => 'Temps', 'short' => 'Temps', 'url' => '/design/time', 'icon' => 'clock', 'key' => 'time'],
        ['label' => 'Réglages', 'short' => 'Réglages', 'url' => '/design/settings', 'icon' => 'settings', 'key' => 'settings'],
    ];

    public function dashboard(Request $request): Response
    {
        return $this->render('pages/dashboard', 'Tableau de bord', 'dashboard');
    }

    public function clients(Request $request): Response
    {
        return $this->render('pages/clients/index', 'Clients', 'clients');
    }

    public function client(Request $request, string $id): Response
    {
        return $this->render('pages/clients/show', 'Fiche client', 'clients', ['recordId' => $id]);
    }

    public function projects(Request $request): Response
    {
        return $this->render('pages/projects/index', 'Projets', 'projects');
    }

    public function project(Request $request, string $id): Response
    {
        return $this->render('pages/projects/show', 'Villa Sidi Bou Saïd', 'projects', ['recordId' => $id]);
    }

    public function documents(Request $request): Response
    {
        return $this->render('pages/documents/index', 'Documents', 'documents');
    }

    public function document(Request $request, string $id): Response
    {
        return $this->render('pages/documents/show', 'Devis PA-2025-018', 'documents', ['recordId' => $id]);
    }

    public function time(Request $request): Response
    {
        return $this->render('pages/time/index', 'Temps & coûts', 'time');
    }

    public function settings(Request $request): Response
    {
        return $this->render('pages/settings/index', 'Réglages du cabinet', 'settings');
    }

    public function modules(Request $request): Response
    {
        return $this->render('pages/settings/modules', 'Modules', 'settings');
    }

    public function login(Request $request): Response
    {
        return Response::html(View::render('pages/auth/login', [
            'title' => 'Connexion',
            'devOnly' => true,
            'demoMode' => true, 'returnTo' => '/dashboard',
        ], 'layouts/auth'));
    }

    public function styleguide(Request $request): Response
    {
        return $this->render('pages/styleguide', 'Bibliothèque de composants', 'settings');
    }

    /**
     * Prépare le contexte partagé du layout et rend un écran de démonstration.
     *
     * @param array<string,mixed> $data
     */
    private function render(string $view, string $title, string $activeNav, array $data = []): Response
    {
        $navigation = array_map(static function (array $item): array {
            $item['url'] = url($item['url']);
            return $item;
        }, self::NAVIGATION);

        return Response::html(View::render($view, $data + [
            'title' => $title,
            'pageTitle' => $title,
            'activeNav' => $activeNav,
            'navigation' => $navigation,
            'workspace' => ['name' => 'Atelier Souissi', 'location' => 'Tunis', 'plan' => 'Plan Studio'],
            'userProfile' => ['name' => 'Amel Trabelsi', 'initials' => 'AT'],
            'devOnly' => true,
        ]));
    }
}
