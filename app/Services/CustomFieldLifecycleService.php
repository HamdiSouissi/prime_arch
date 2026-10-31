<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\CustomFieldRepository;
use App\Models\ProjectFileRepository;
use App\Models\ProjectImageRepository;
use InvalidArgumentException;
use Throwable;

/**
 * Cycle de vie d'un champ personnalisé : mesure de son contenu et suppression complète.
 *
 * Une définition ne vit jamais seule : les types saisis stockent leur valeur dans la colonne
 * JSON `custom_fields` de chaque fiche, les types « image » et « fichier » dans
 * `project_images` / `project_files` avec les fichiers privés correspondants. La suppression
 * d'une définition retire donc, dans l'ordre : les fichiers privés, leurs métadonnées, les
 * valeurs JSON, puis la définition. L'ordre garantit qu'une panne au milieu du processus laisse
 * des métadonnées sans fichier (rejouable) plutôt que l'inverse.
 */
final class CustomFieldLifecycleService
{
    public function __construct(
        private ?CustomFieldRepository $fields = null,
        private ?ProjectImageRepository $images = null,
        private ?ProjectFileRepository $files = null,
        private ?ProjectImageStorage $imageStorage = null,
        private ?ProjectFileStorage $fileStorage = null
    ) {
        $this->fields ??= new CustomFieldRepository();
    }

    /**
     * Contenu d'un champ : valeurs saisies, images et fichiers rattachés.
     *
     * Les totaux servent à la confirmation de suppression et au refus de changer le type d'un
     * champ déjà alimenté. Une base non migrée renvoie zéro plutôt qu'une erreur : la page des
     * réglages doit rester consultable.
     *
     * @param array<string,mixed> $field
     * @return array{values:int,images:int,files:int,total:int}
     */
    public function impact(array $field): array
    {
        $key = (string) ($field['field_key'] ?? '');
        $type = (string) ($field['field_type'] ?? '');
        $entityType = (string) ($field['entity_type'] ?? '');
        $values = 0;
        $images = 0;
        $files = 0;

        if ($type !== 'image' && $type !== 'file') {
            try {
                $values = $this->fields->countStoredValues($entityType, $key);
            } catch (Throwable $error) {
                error_log('Champs personnalisés : comptage des valeurs impossible — ' . $error->getMessage());
            }
        }
        if ($type === 'image') {
            try {
                $images = count($this->imageRepository()->allForCustomField($key));
            } catch (Throwable $error) {
                error_log('Champs personnalisés : comptage des images impossible — ' . $error->getMessage());
            }
        }
        if ($type === 'file') {
            try {
                $files = count($this->fileRepository()->allForCustomField($key));
            } catch (Throwable $error) {
                error_log('Champs personnalisés : comptage des fichiers impossible — ' . $error->getMessage());
            }
        }

        return ['values' => $values, 'images' => $images, 'files' => $files, 'total' => $values + $images + $files];
    }

    /**
     * Supprime une définition et tout son contenu.
     *
     * @param array<string,mixed> $field
     * @return array{values:int,images:int,files:int,total:int} contenu réellement supprimé
     */
    public function delete(array $field): array
    {
        $fieldId = (int) ($field['id'] ?? 0);
        $key = (string) ($field['field_key'] ?? '');
        $type = (string) ($field['field_type'] ?? '');
        if ($fieldId < 1 || $key === '') {
            throw new InvalidArgumentException(__('custom_fields.invalid'));
        }

        $impact = ['values' => 0, 'images' => 0, 'files' => 0, 'total' => 0];

        // 1. Fichiers privés puis métadonnées, champ par champ rattaché à cette clé.
        if ($type === 'image') {
            $images = $this->imageRepository()->allForCustomField($key);
            foreach ($images as $image) {
                $this->imageStorage()->remove((int) $image['project_id'], (string) $image['stored_name']);
            }
            $impact['images'] = $this->imageRepository()->deleteForCustomField($key);
        }
        if ($type === 'file') {
            $files = $this->fileRepository()->allForCustomField($key);
            foreach ($files as $file) {
                $this->fileStorage()->remove((int) $file['project_id'], (string) $file['stored_name']);
            }
            $impact['files'] = $this->fileRepository()->deleteForCustomField($key);
        }

        // 2. Valeurs JSON des fiches (types saisis).
        if ($type !== 'image' && $type !== 'file') {
            $impact['values'] = $this->fields->removeStoredValues((string) $field['entity_type'], $key);
        }

        // 3. La définition elle-même.
        $this->fields->delete($fieldId);
        $impact['total'] = $impact['values'] + $impact['images'] + $impact['files'];

        return $impact;
    }

    private function imageRepository(): ProjectImageRepository
    {
        return $this->images ??= new ProjectImageRepository();
    }

    private function fileRepository(): ProjectFileRepository
    {
        return $this->files ??= new ProjectFileRepository();
    }

    private function imageStorage(): ProjectImageStorage
    {
        return $this->imageStorage ??= new ProjectImageStorage();
    }

    private function fileStorage(): ProjectFileStorage
    {
        return $this->fileStorage ??= new ProjectFileStorage();
    }
}
