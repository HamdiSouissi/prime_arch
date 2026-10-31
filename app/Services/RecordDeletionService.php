<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ClientRepository;
use App\Models\DocumentRepository;
use App\Models\ProjectFileRepository;
use App\Models\ProjectImageRepository;
use App\Models\ProjectRepository;
use App\Models\TimeEntryRepository;
use Throwable;

/**
 * Suppression définitive d'une fiche client ou projet, avec son contenu.
 *
 * Deux règles guident ce service :
 *
 * 1. **Rien de financier ne disparaît par effet de bord.** Un client qui porte des projets, ou
 *    une fiche à laquelle un devis ou une facture est rattaché, n'est pas supprimable : la
 *    suppression est refusée avec les compteurs, et l'utilisateur traite d'abord ces documents.
 *    Un numéro de facture émis ne doit jamais pouvoir s'évaporer parce qu'on a supprimé une
 *    fiche par erreur.
 * 2. **Ce qui appartient à la fiche part avec elle.** Images privées, plans techniques, temps
 *    saisi et phases affectées d'un projet suivent sa suppression ; les fichiers du disque sont
 *    retirés avant les métadonnées, pour qu'une panne laisse au pire des lignes sans fichier
 *    (visibles et rejouables) plutôt que des fichiers orphelins invisibles.
 */
final class RecordDeletionService
{
    public function __construct(
        private ?ClientRepository $clients = null,
        private ?ProjectRepository $projects = null,
        private ?DocumentRepository $documents = null,
        private ?TimeEntryRepository $timeEntries = null,
        private ?ProjectImageRepository $images = null,
        private ?ProjectFileRepository $files = null,
        private ?ProjectImageStorage $imageStorage = null,
        private ?ProjectFileStorage $fileStorage = null
    ) {
        $this->clients ??= new ClientRepository();
    }

    /**
     * Contenu d'une fiche client et motifs de refus éventuels.
     *
     * @return array{projects:int,documents:int,contacts:int,blocked:bool}
     */
    public function clientImpact(int $clientId): array
    {
        $projects = $this->clientRepository()->countProjects($clientId);
        $documents = $this->documentRepository()->countForClient($clientId);
        $contacts = $this->clientRepository()->countContacts($clientId);

        return [
            'projects' => $projects,
            'documents' => $documents,
            'contacts' => $contacts,
            'blocked' => $projects > 0 || $documents > 0,
        ];
    }

    /**
     * Supprime un client et son carnet de contacts, ou explique pourquoi il est conservé.
     *
     * @return array{deleted:bool,projects:int,documents:int,contacts:int,blocked:bool}
     */
    public function deleteClient(int $clientId): array
    {
        $impact = $this->clientImpact($clientId);
        if ($impact['blocked']) {
            return ['deleted' => false] + $impact;
        }
        $deleted = $this->clientRepository()->delete($clientId);

        return ['deleted' => $deleted] + $impact;
    }

    /**
     * Contenu d'une fiche projet et motifs de refus éventuels.
     *
     * @return array{documents:int,images:int,files:int,entries:int,hours:string,assignments:int,blocked:bool}
     */
    public function projectImpact(int $projectId): array
    {
        $documents = $this->documentRepository()->countForProject($projectId);
        $entries = $this->timeEntryRepository()->countForProject($projectId);

        return [
            'documents' => $documents,
            'images' => count($this->imageRepository()->allForProject($projectId)),
            'files' => count($this->fileRepository()->allForProject($projectId)),
            'entries' => $entries,
            'hours' => $this->timeEntryRepository()->hoursForProject($projectId),
            'assignments' => $this->projectRepository()->countAssignments($projectId),
            'blocked' => $documents > 0,
        ];
    }

    /**
     * Supprime un projet, ses fichiers privés et les données qui lui appartiennent.
     *
     * @return array{deleted:bool,documents:int,images:int,files:int,entries:int,hours:string,assignments:int,blocked:bool}
     */
    public function deleteProject(int $projectId): array
    {
        $impact = $this->projectImpact($projectId);
        if ($impact['blocked']) {
            return ['deleted' => false] + $impact;
        }

        // 1. Fichiers du disque : images de chantier et plans techniques, champs compris.
        foreach ($this->imageRepository()->allForProject($projectId) as $image) {
            $this->imageStorageService()->remove($projectId, (string) ($image['stored_name'] ?? ''));
        }
        foreach ($this->fileRepository()->allForProject($projectId) as $file) {
            $this->fileStorageService()->remove($projectId, (string) ($file['stored_name'] ?? ''));
        }

        // 2. Dépendances puis la fiche, dans une seule transaction : aucune ligne orpheline ne
        //    doit rester si l'une des suppressions échoue.
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }
            $impact['images'] = $this->imageRepository()->deleteForProject($projectId);
            $impact['files'] = $this->fileRepository()->deleteForProject($projectId);
            $impact['entries'] = $this->timeEntryRepository()->deleteForProject($projectId);
            $impact['assignments'] = $this->projectRepository()->deleteAssignments($projectId);
            $deleted = $this->projectRepository()->delete($projectId);
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        return ['deleted' => $deleted] + $impact;
    }

    private function clientRepository(): ClientRepository
    {
        return $this->clients ??= new ClientRepository();
    }

    private function projectRepository(): ProjectRepository
    {
        return $this->projects ??= new ProjectRepository();
    }

    private function documentRepository(): DocumentRepository
    {
        return $this->documents ??= new DocumentRepository();
    }

    private function timeEntryRepository(): TimeEntryRepository
    {
        return $this->timeEntries ??= new TimeEntryRepository();
    }

    private function imageRepository(): ProjectImageRepository
    {
        return $this->images ??= new ProjectImageRepository();
    }

    private function fileRepository(): ProjectFileRepository
    {
        return $this->files ??= new ProjectFileRepository();
    }

    private function imageStorageService(): ProjectImageStorage
    {
        return $this->imageStorage ??= new ProjectImageStorage();
    }

    private function fileStorageService(): ProjectFileStorage
    {
        return $this->fileStorage ??= new ProjectFileStorage();
    }
}
