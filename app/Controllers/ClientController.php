<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\ClientRepository;
use App\Models\CustomFieldRepository;
use App\Models\DocumentRepository;
use App\Models\GeographyRepository;
use App\Services\AuditLogger;
use App\Services\ClientService;
use App\Services\ViewContext;
use Throwable;

/**
 * Gestion des fiches clients, adresses tunisiennes et personnes de contact.
 */
final class ClientController
{
    public function index(Request $request): Response
    {
        if (!Auth::can('clients.view')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $search = self::queryString($request, 'q');
        $status = self::queryString($request, 'status');
        $type = self::queryString($request, 'type');
        if (!in_array($status, ['', 'prospect', 'active', 'archived'], true)) {
            $status = '';
        }
        if (!in_array($type, ['', 'individual', 'company', 'organization'], true)) {
            $type = '';
        }
        $page = self::page($request->query('page'));
        $result = (new ClientRepository())->paginate([
            'search' => mb_substr($search, 0, 100, 'UTF-8'),
            'status' => $status,
            'type' => $type,
        ], 20, ($page - 1) * 20);
        $context = (new ViewContext())->authenticated(__('clients.title'), 'clients');
        return Response::html(View::render('pages/clients/index', $context + [
            'clients' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pageCount' => max(1, (int) ceil($result['total'] / 20)),
            'filters' => ['q' => $search, 'status' => $status, 'type' => $type],
            'canViewProjects' => Auth::can('projects.view'),
            'canManage' => Auth::can('clients.manage'),
        ]));
    }

    public function createForm(Request $request): Response
    {
        if (!Auth::can('clients.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $values = Session::pullFlash('clients.values', []);
        return $this->formResponse(
            is_array($values) ? $values : [],
            null,
            Session::pullFlash('clients.errors', []),
            __('clients.create')
        );
    }

    public function create(Request $request): Response
    {
        if (!Auth::can('clients.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $input = self::formInput($request);
        $validated = (new ClientService())->validate($input, $request->input('custom_fields', []));
        if ($validated['errors'] !== []) {
            Session::flash('clients.errors', $validated['errors']);
            Session::flash('clients.values', $validated['values']);
            return Response::redirect(url('/clients/new'));
        }
        try {
            $id = (new ClientRepository())->create($validated['values'], Auth::id());
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('clients.errors', ['general' => __('clients.save_failed')]);
            Session::flash('clients.values', $validated['values']);
            return Response::redirect(url('/clients/new'));
        }
        (new AuditLogger())->record('clients.created', 'client', $id, [], Auth::id());
        Session::flash('clients.notice', __('clients.created'));
        return Response::redirect(url('/clients/' . $id));
    }

    public function show(Request $request, string $id): Response
    {
        if (!Auth::can('clients.view')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $clientId = filter_var($id, FILTER_VALIDATE_INT);
        $repository = new ClientRepository();
        $client = $clientId !== false && $clientId > 0 ? $repository->findById((int) $clientId) : null;
        if ($client === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        $context = (new ViewContext())->authenticated((string) $client['display_name'], 'clients');
        $canViewDocuments = Auth::can('documents.view') || Auth::can('documents.manage')
            || Auth::can('documents.issue') || Auth::can('documents.create')
            || Auth::can('documents.payments.manage') || Auth::can('documents.templates.manage')
            || Auth::can('settings.manage');
        return Response::html(View::render('pages/clients/show', $context + [
            'documents' => $canViewDocuments
                ? (new DocumentRepository())->forClient((int) $client['id'])
                : [],
            'canViewDocuments' => $canViewDocuments,
            'client' => $client,
            'contacts' => $repository->contacts((int) $client['id']),
            'projects' => Auth::can('projects.view') ? $repository->projects((int) $client['id']) : [],
            'canManageProjects' => Auth::can('projects.manage'),
            'canViewProjects' => Auth::can('projects.view'),
            'customFields' => (new CustomFieldRepository())->all('client', true),
            'errors' => Session::pullFlash('clients.errors', []),
            'contactValues' => Session::pullFlash('clients.contact_values', []),
            'notice' => Session::pullFlash('clients.notice'),
            'canManage' => Auth::can('clients.manage'),
        ]));
    }

    public function edit(Request $request, string $id): Response
    {
        if (!Auth::can('clients.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $clientId = filter_var($id, FILTER_VALIDATE_INT);
        $client = $clientId !== false && $clientId > 0 ? (new ClientRepository())->findById((int) $clientId) : null;
        if ($client === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        $values = Session::pullFlash('clients.values', []);
        return $this->formResponse(
            is_array($values) && $values !== [] ? $values : $client,
            $client,
            Session::pullFlash('clients.errors', []),
            __('clients.edit')
        );
    }

    public function update(Request $request, string $id): Response
    {
        if (!Auth::can('clients.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $clientId = filter_var($id, FILTER_VALIDATE_INT);
        $repository = new ClientRepository();
        $client = $clientId !== false && $clientId > 0 ? $repository->findById((int) $clientId) : null;
        if ($client === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        $input = self::formInput($request);
        $validated = (new ClientService())->validate($input, $request->input('custom_fields', []), $client['custom_fields']);
        if ($validated['errors'] !== []) {
            Session::flash('clients.errors', $validated['errors']);
            Session::flash('clients.values', $validated['values']);
            return Response::redirect(url('/clients/' . $clientId . '/edit'));
        }
        try {
            $repository->update((int) $clientId, $validated['values']);
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('clients.errors', ['general' => __('clients.save_failed')]);
            Session::flash('clients.values', $validated['values']);
            return Response::redirect(url('/clients/' . $clientId . '/edit'));
        }
        (new AuditLogger())->record('clients.updated', 'client', (int) $clientId, [], Auth::id());
        Session::flash('clients.notice', __('clients.updated'));
        return Response::redirect(url('/clients/' . $clientId));
    }

    public function createContact(Request $request, string $id): Response
    {
        return $this->saveContact($request, $id, null);
    }

    public function updateContact(Request $request, string $id, string $contactId): Response
    {
        return $this->saveContact($request, $id, $contactId);
    }

    public function deleteContact(Request $request, string $id, string $contactId): Response
    {
        if (!Auth::can('clients.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $clientId = filter_var($id, FILTER_VALIDATE_INT);
        $personId = filter_var($contactId, FILTER_VALIDATE_INT);
        if ($clientId === false || $clientId < 1 || $personId === false || $personId < 1) {
            return Response::redirect(url('/clients'));
        }
        try {
            $deleted = (new ClientRepository())->deleteContact((int) $clientId, (int) $personId);
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('clients.errors', ['general' => __('clients.save_failed')]);
            return Response::redirect(url('/clients/' . $clientId));
        }
        if ($deleted) {
            (new AuditLogger())->record('clients.contact.deleted', 'client_contact', (int) $personId, [
                'client_id' => (int) $clientId,
                'contact_action' => 'deleted',
            ], Auth::id());
        }
        Session::flash('clients.notice', $deleted ? __('clients.contact_deleted') : __('clients.invalid_contact'));
        return Response::redirect(url('/clients/' . $clientId));
    }

    private function saveContact(Request $request, string $id, ?string $contactId): Response
    {
        if (!Auth::can('clients.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $clientId = filter_var($id, FILTER_VALIDATE_INT);
        $personId = $contactId === null ? null : filter_var($contactId, FILTER_VALIDATE_INT);
        $repository = new ClientRepository();
        $client = $clientId !== false && $clientId > 0 ? $repository->findById((int) $clientId) : null;
        if ($client === null || ($contactId !== null && ($personId === false || $personId < 1))) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        $contact = [
            'full_name' => self::inputString($request, 'full_name'),
            'job_title' => self::inputString($request, 'job_title'),
            'email' => mb_strtolower(self::inputString($request, 'email'), 'UTF-8'),
            'phone' => self::inputString($request, 'phone'),
            'is_primary' => in_array($request->input('is_primary'), ['1', 1, true, 'on'], true),
        ];
        $errors = self::validateContact($contact);
        if ($errors !== []) {
            $contactErrors = ['contact' => $errors];
            if ($personId !== null) {
                $contactErrors['contact_id'] = (int) $personId;
            }
            Session::flash('clients.errors', $contactErrors);
            Session::flash('clients.contact_values', $contact);
            return Response::redirect(url('/clients/' . $clientId));
        }
        $contact['job_title'] = $contact['job_title'] !== '' ? $contact['job_title'] : null;
        $contact['email'] = $contact['email'] !== '' ? $contact['email'] : null;
        $contact['phone'] = $contact['phone'] !== '' ? $contact['phone'] : null;
        try {
            if ($contactId === null) {
                $createdContactId = $repository->createContact((int) $clientId, $contact);
                (new AuditLogger())->record('clients.contact.created', 'client_contact', $createdContactId, [
                    'client_id' => (int) $clientId,
                    'contact_action' => 'created',
                    'is_primary' => $contact['is_primary'],
                ], Auth::id());
                Session::flash('clients.notice', __('clients.contact_created'));
            } elseif (!$repository->updateContact((int) $clientId, (int) $personId, $contact)) {
                Session::flash('clients.errors', ['general' => __('clients.invalid_contact')]);
            } else {
                (new AuditLogger())->record('clients.contact.updated', 'client_contact', (int) $personId, [
                    'client_id' => (int) $clientId,
                    'contact_action' => 'updated',
                    'is_primary' => $contact['is_primary'],
                ], Auth::id());
                Session::flash('clients.notice', __('clients.contact_updated'));
            }
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('clients.errors', ['general' => __('clients.save_failed')]);
        }
        return Response::redirect(url('/clients/' . $clientId));
    }

    /** @param array<string,mixed> $contact
     *  @return array<string,string>
     */
    private static function validateContact(array $contact): array
    {
        $errors = [];
        if (mb_strlen($contact['full_name'], 'UTF-8') < 2 || mb_strlen($contact['full_name'], 'UTF-8') > 150) {
            $errors['full_name'] = __('clients.invalid_contact');
        }
        if (mb_strlen($contact['job_title'], 'UTF-8') > 100) {
            $errors['job_title'] = __('clients.invalid_contact');
        }
        if ($contact['email'] !== '' && (!filter_var($contact['email'], FILTER_VALIDATE_EMAIL) || strlen($contact['email']) > 190)) {
            $errors['email'] = __('clients.invalid_contact');
        }
        if (mb_strlen($contact['phone'], 'UTF-8') > 32
            || ($contact['phone'] !== '' && !preg_match((string) config('tunisia.phone.pattern'), $contact['phone']))
        ) {
            $errors['phone'] = __('clients.invalid_contact');
        }
        return $errors;
    }

    /** @param array<string,mixed> $values
     *  @param array<string,mixed> $client
     *  @param array<string,mixed> $errors
     */
    private function formResponse(array $values, ?array $client, array $errors, string $title): Response
    {
        $governorateId = ctype_digit((string) ($values['governorate_id'] ?? ''))
            ? (int) $values['governorate_id']
            : 0;
        $context = (new ViewContext())->authenticated($title, 'clients');
        return Response::html(View::render('pages/clients/form', $context + [
            'client' => $client,
            'values' => $values,
            'errors' => $errors,
            'customFields' => (new CustomFieldRepository())->all('client', true),
            'canConfigureFields' => Auth::can('clients.manage'),
            'governorates' => (new GeographyRepository())->governorates(),
            'delegations' => $governorateId > 0 ? (new GeographyRepository())->delegations($governorateId) : [],
            'title' => $title,
            'notice' => Session::pullFlash('clients.notice'),
        ]));
    }

    /** @return array<string,mixed> */
    private static function formInput(Request $request): array
    {
        $values = [];
        foreach ([
            'client_type', 'display_name', 'legal_name', 'tax_id', 'email', 'phone',
            'address_line', 'city', 'governorate_id', 'delegation_id', 'postal_code',
            'status', 'notes',
        ] as $key) {
            $values[$key] = self::inputString($request, $key);
        }
        return $values;
    }

    private static function inputString(Request $request, string $key): string
    {
        $value = $request->input($key, '');
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    private static function queryString(Request $request, string $key): string
    {
        $value = $request->query($key, '');
        return is_string($value) ? trim($value) : '';
    }

    private static function page(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT);
        return $page === false || $page < 1 ? 1 : min($page, 100000);
    }
}
