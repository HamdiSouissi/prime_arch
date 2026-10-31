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
use App\Models\ProjectRepository;
use App\Services\AuditLogger;
use App\Services\CustomFieldService;
use PDOException;
use Throwable;

/**
 * Champs personnalisés gérés depuis la fiche elle-même.
 *
 * Une définition créée dans Réglages › Champs personnalisés peut rester inactive : elle est
 * alors invisible sur les fiches et dans les formulaires. Plutôt que d'imposer un aller-retour
 * par les réglages, la fiche client ou projet rappelle les définitions en attente et permet de
 * les activer, ou d'en créer une immédiatement.
 *
 * L'activation et la création restent des modifications de schéma : elles exigent les mêmes
 * droits que la page de réglages (`clients.manage`, `projects.configure`) et laissent la même
 * trace d'audit (`custom_fields.toggled`, `custom_fields.created`).
 */
final class RecordCustomFieldController
{
    /** Crée une définition de champ pour un client, depuis sa fiche. */
    public function storeClient(Request $request, string $id): Response
    {
        if (!Auth::can('clients.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $clientId = self::existingClientId($id);
        if ($clientId === null) {
            return self::notFound();
        }

        return $this->store($request, 'client', $clientId);
    }

    /** Active une définition existante (créée puis désactivée) depuis une fiche client. */
    public function activateClient(Request $request, string $id, string $fieldId): Response
    {
        if (!Auth::can('clients.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $clientId = self::existingClientId($id);
        if ($clientId === null) {
            return self::notFound();
        }

        return $this->activate('client', $fieldId, $clientId);
    }

    /** Crée une définition de champ pour un projet, depuis sa fiche. */
    public function storeProject(Request $request, string $id): Response
    {
        if (!Auth::can('projects.configure')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = self::existingProjectId($id);
        if ($projectId === null) {
            return self::notFound();
        }

        return $this->store($request, 'project', $projectId);
    }

    /** Active une définition existante (créée puis désactivée) depuis une fiche projet. */
    public function activateProject(Request $request, string $id, string $fieldId): Response
    {
        if (!Auth::can('projects.configure')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = self::existingProjectId($id);
        if ($projectId === null) {
            return self::notFound();
        }

        return $this->activate('project', $fieldId, $projectId);
    }

    /** Renseigne la valeur d'un champ depuis la fiche client, sans passer par le formulaire complet. */
    public function saveValueClient(Request $request, string $id, string $field): Response
    {
        if (!Auth::can('clients.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $clientId = self::existingClientId($id);
        if ($clientId === null) {
            return self::notFound();
        }
        $client = (new ClientRepository())->findById($clientId);
        if ($client === null) {
            return self::notFound();
        }

        return $this->saveValue($request, 'client', $clientId, $field, (array) ($client['custom_fields'] ?? []));
    }

    /** Renseigne la valeur d'un champ depuis la fiche projet. */
    public function saveValueProject(Request $request, string $id, string $field): Response
    {
        if (!Auth::can('projects.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = self::existingProjectId($id);
        if ($projectId === null) {
            return self::notFound();
        }
        $project = (new ProjectRepository())->findById($projectId);
        if ($project === null) {
            return self::notFound();
        }

        return $this->saveValue($request, 'project', $projectId, $field, (array) ($project['custom_fields'] ?? []));
    }

    /**
     * Enregistre une valeur isolée, validée par le même service que le formulaire complet.
     *
     * Une seule définition est contrôlée : un autre champ obligatoire resté vide ne bloque pas
     * cette saisie. Le type « image » et le type « fichier » se remplissent par téléversement et
     * sont refusés ici.
     *
     * @param array<string,mixed> $existingValues valeurs déjà enregistrées sur la fiche
     */
    private function saveValue(
        Request $request,
        string $entityType,
        int $recordId,
        string $field,
        array $existingValues
    ): Response {
        $key = trim($field);
        if ($key === '' || preg_match('/^[a-z][a-z0-9_]{1,79}$/', $key) !== 1) {
            return self::notFound();
        }
        $result = (new CustomFieldService())->validateFieldValue(
            $entityType,
            $key,
            $request->input('value', ''),
            $existingValues
        );
        if ($result['errors'] !== []) {
            return self::backToRecord($entityType, $recordId, null, array_values($result['errors']));
        }
        try {
            $entityType === 'client'
                ? (new ClientRepository())->updateCustomFields($recordId, $result['values'])
                : (new ProjectRepository())->updateCustomFields($recordId, $result['values']);
        } catch (Throwable $error) {
            error_log((string) $error);

            return self::backToRecord($entityType, $recordId, null, [__('custom_fields.save_failed')]);
        }
        (new AuditLogger())->record('custom_fields.value_updated', 'custom_field', null, [
            'entity_type' => $entityType,
            'field_key' => $key,
            'record_id' => $recordId,
        ], Auth::id());

        return self::backToRecord($entityType, $recordId, __('custom_fields.value_saved'), [], 'custom-field-' . $key);
    }

    /**
     * Création rapide : seuls le libellé, le type et les options sont demandés.
     *
     * La clé technique, invisible dans ce formulaire, est dérivée du libellé puis rendue unique
     * pour le type de fiche : elle reste stable et lisible dans les réglages. L'ordre d'affichage
     * place le nouveau champ après les existants. Le reste des règles est celui de la page de
     * réglages (`CustomFieldService::validateDefinition`), y compris le refus d'un type absent
     * de la base avant la migration 0015.
     */
    private function store(Request $request, string $entityType, int $recordId): Response
    {
        $repository = new CustomFieldRepository();
        $definitions = $repository->all($entityType);
        $label = self::inputString($request, 'label');
        $typeInput = self::inputString($request, 'field_type');
        $input = [
            'entity_type' => $entityType,
            'field_key' => self::generateKey($label, $definitions),
            'label' => $label,
            'field_type' => $typeInput !== '' ? $typeInput : 'text',
            'options' => self::inputString($request, 'options'),
            'is_required' => self::inputString($request, 'is_required'),
            'sort_order' => (string) self::nextSortOrder($definitions),
        ];
        $validated = (new CustomFieldService())->validateDefinition($input);
        if ($validated['errors'] !== []) {
            // Les messages portent sur un formulaire réduit : on les remonte tels quels, dans
            // l'ordre où ils ont été détectés, sans exposer la clé générée.
            return self::backToRecord($entityType, $recordId, null, array_values($validated['errors']));
        }

        $acceptedTypes = $repository->acceptedTypes();
        if ($acceptedTypes !== null
            && !in_array((string) $validated['data']['field_type'], $acceptedTypes, true)
        ) {
            return self::backToRecord($entityType, $recordId, null, [__('custom_fields.type_requires_migration')]);
        }

        try {
            $fieldId = $repository->create($validated['data']);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                return self::backToRecord($entityType, $recordId, null, [__('custom_fields.key_exists')]);
            }
            error_log((string) $error);

            return self::backToRecord($entityType, $recordId, null, [__('custom_fields.save_failed')]);
        } catch (Throwable $error) {
            error_log((string) $error);

            return self::backToRecord($entityType, $recordId, null, [__('custom_fields.save_failed')]);
        }

        // La création rapide active le champ dans la foulée : sans cela la fiche ne montrerait
        // rien de neuf et l'utilisateur croirait l'ajout perdu.
        $repository->setActive($fieldId, true);
        (new AuditLogger())->record('custom_fields.created', 'custom_field', $fieldId, [
            'entity_type' => $entityType,
            'field_type' => (string) $validated['data']['field_type'],
            'is_required' => (bool) $validated['data']['is_required'],
            'source' => 'record',
        ], Auth::id());

        return self::backToRecord($entityType, $recordId, __('custom_fields.quick_added', [
            'label' => (string) $validated['data']['label'],
        ]), [], 'custom-field-' . (string) $validated['data']['field_key']);
    }

    /** Réactive une définition : contrôles d'appartenance, de droits, journalisation et retour. */
    private function activate(string $entityType, string $fieldId, int $recordId): Response
    {
        $repository = new CustomFieldRepository();
        $id = filter_var($fieldId, FILTER_VALIDATE_INT);
        $field = $id !== false && $id > 0 ? $repository->findById((int) $id) : null;
        // Une définition d'un autre type de fiche n'a rien à faire ici : même réponse qu'une
        // définition absente, pour ne pas révéler son existence.
        if ($field === null || (string) $field['entity_type'] !== $entityType) {
            return self::notFound();
        }
        if (!empty($field['is_active'])) {
            return self::backToRecord($entityType, $recordId, null, []);
        }
        try {
            $repository->setActive((int) $field['id'], true);
        } catch (Throwable $error) {
            error_log((string) $error);

            return self::backToRecord($entityType, $recordId, null, [__('custom_fields.save_failed')]);
        }
        (new AuditLogger())->record('custom_fields.toggled', 'custom_field', (int) $field['id'], [
            'entity_type' => $entityType,
            'is_active' => true,
            'source' => 'record',
        ], Auth::id());

        return self::backToRecord($entityType, $recordId, __('custom_fields.activated', [
            'label' => (string) $field['label'],
        ]), []);
    }

    /**
     * Clé technique dérivée du libellé, unique pour ce type de fiche.
     *
     * Un libellé en arabe ne produit aucun caractère latin exploitable : la clé devient alors
     * `champ`, suffixée si besoin. Les clés restent conformes au format accepté partout ailleurs
     * (`^[a-z][a-z0-9_]{1,79}$`), y compris pour les chemins JSON de suppression.
     *
     * @param array<int,array<string,mixed>> $definitions
     */
    private static function generateKey(string $label, array $definitions): string
    {
        $base = self::slug($label);
        $base = $base === '' ? 'champ' : $base;
        $taken = array_map(static fn (array $field): string => (string) $field['field_key'], $definitions);
        if (!in_array($base, $taken, true)) {
            return $base;
        }
        for ($suffix = 2; $suffix < 1000; $suffix++) {
            $candidate = substr($base, 0, 76) . '_' . $suffix;
            if (!in_array($candidate, $taken, true)) {
                return $candidate;
            }
        }

        return substr($base, 0, 68) . '_' . bin2hex(random_bytes(4));
    }

    /** Forme « slug » d'un libellé : minuscules ASCII, chiffres et tirets bas. */
    private static function slug(string $label): string
    {
        static $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ä' => 'a', 'Ã' => 'a', 'Å' => 'a',
            'ç' => 'c', 'Ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'Î' => 'i', 'Ï' => 'i', 'Í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
            'Ô' => 'o', 'Ö' => 'o', 'Ó' => 'o', 'Õ' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u', 'Ú' => 'u',
            'ÿ' => 'y', 'Ÿ' => 'y', 'ñ' => 'n', 'Ñ' => 'n',
            'œ' => 'oe', 'Œ' => 'oe', 'æ' => 'ae', 'Æ' => 'ae', 'ß' => 'ss',
            // L'élision sépare les mots (« Travaux d'été » → travaux_d_ete) ; une esperluette
            // devient « et », plus lisible qu'un simple tiret bas.
            '’' => ' ', '\'' => ' ', 'ʼ' => ' ', '&' => ' et ',
        ];
        $ascii = strtr($label, $map);
        $slug = preg_replace('/[^A-Za-z0-9]+/', '_', $ascii) ?? '';
        $slug = strtolower(trim($slug, '_'));
        if ($slug !== '' && preg_match('/^[0-9]/', $slug) === 1) {
            // Une clé ne commence jamais par un chiffre (format imposé par la validation).
            $slug = 'c_' . $slug;
        }

        return preg_match('/^[a-z][a-z0-9_]{1,79}$/', $slug) === 1 ? $slug : '';
    }

    /** @param array<int,array<string,mixed>> $definitions */
    private static function nextSortOrder(array $definitions): int
    {
        $max = 0;
        foreach ($definitions as $definition) {
            $max = max($max, (int) ($definition['sort_order'] ?? 0));
        }

        return min($max + 10, 9999);
    }

    /**
     * Retour sur la fiche, au bloc des champs personnalisés, avec le message éventuel.
     *
     * `$anchor` cible une ligne précise : après une création rapide, la fiche s'ouvre sur le
     * nouveau champ, qui apparaît même sans valeur.
     */
    private static function backToRecord(
        string $entityType,
        int $recordId,
        ?string $notice,
        array $errors,
        ?string $anchor = null
    ): Response {
        $prefix = $entityType === 'client' ? 'clients' : 'projects';
        if ($notice !== null) {
            Session::flash($prefix . '.custom_field_notice', $notice);
        }
        if ($errors !== []) {
            Session::flash($prefix . '.custom_field_errors', $errors);
        }
        $anchor ??= $entityType === 'client' ? 'client-custom-title' : 'project-custom-title';
        $path = $entityType === 'client' ? '/clients/' : '/projects/';

        return Response::redirect(url($path . $recordId) . '#' . $anchor);
    }

    private static function existingClientId(string $id): ?int
    {
        $clientId = filter_var($id, FILTER_VALIDATE_INT);
        if ($clientId === false || $clientId < 1) {
            return null;
        }

        return (new ClientRepository())->findById((int) $clientId) === null ? null : (int) $clientId;
    }

    private static function existingProjectId(string $id): ?int
    {
        $projectId = filter_var($id, FILTER_VALIDATE_INT);
        if ($projectId === false || $projectId < 1) {
            return null;
        }

        return (new ProjectRepository())->findById((int) $projectId) === null ? null : (int) $projectId;
    }

    private static function notFound(): Response
    {
        return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
    }

    private static function inputString(Request $request, string $key): string
    {
        $value = $request->input($key, '');

        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
