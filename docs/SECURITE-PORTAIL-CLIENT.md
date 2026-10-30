# Revue de sécurité — module `portail-client`

Document de référence pour le module **Espace client en lecture seule**. Il décrit ce qui est
exposé, les menaces prises en compte, les défenses réellement codées (avec leur emplacement) et
les risques résiduels qui dépendent du noyau ou de l’exploitation.

Le module ajoute des **routes publiques**, c’est-à-dire accessibles sans session. C’est la seule
surface de ce type parmi les modules livrés : tout le reste du catalogue reste derrière
`AuthMiddleware`.

---

## 1. Ce que le module expose, et à qui

Un lien donne accès, en lecture seule, à un périmètre **fermé** :

| Donnée | Visible | Remarque |
|---|---|---|
| Nom et référence du client | Oui | Il s’agit du destinataire du lien |
| Projets du périmètre (nom, référence, type, état, ville, dates) | Oui | Un seul projet si `scope = project` |
| Phases (nom, état, avancement) et avancement pondéré | Oui | Même formule que le noyau |
| Documents `status = issued` (numéro, type, émission, échéance) | Oui si `can_view_documents` | Jamais les brouillons ni les pièces annulées |
| Montants des documents | Oui si `can_view_amounts` | Subordonné à `can_view_documents` |
| Photos de projet | Oui si `can_view_photos` | 12 par projet, servies via le jeton |
| Nom, téléphone et e-mail du cabinet | Oui | Uniquement les coordonnées professionnelles |

**Jamais exposés** : notes internes du client et du projet, adresse, identifiant fiscal,
contacts du client, budgets d’honoraires, heures, coûts, taux horaires, règlements,
avoir/paiements détaillés, autres clients, autres projets hors périmètre, journal d’audit,
fichiers PDF, chemin de stockage des photos, jeton lui-même.

---

## 2. Décisions de conception

### 2.1 Jeton aléatoire plutôt que lien HMAC

Le catalogue proposait « jeton HMAC ». La mise en œuvre retient un **secret aléatoire de
256 bits** (`random_bytes(32)`, 64 caractères hexadécimaux) dont seule l’empreinte SHA-256 est
stockée (`portail_jetons.token_hash`, `UNIQUE`).

Pourquoi : un jeton signé permettrait de vérifier l’URL sans base, mais la révocation et la
portée exigeraient de toute façon une lecture en base. Le jeton aléatoire apporte donc la même
garantie, avec un avantage décisif — **la révocation est immédiate** et une fuite de la base ne
permet pas de reconstituer les liens. Un HMAC sur un identifiant public aurait, lui, laissé
l’identifiant visible dans les journaux et les historiques.

Le SHA-256 simple suffit ici : le secret est déjà à 256 bits, il n’est pas devinable par force
brute ; il ne s’agit pas d’un mot de passe humain.

### 2.2 Portée et droits explicites

- `scope = project` (par défaut) : un seul projet.
- `scope = client` : tous les projets du client, à choisir consciemment.
- Trois droits distincts, à cocher : documents, montants, photos. Les montants ne peuvent pas
  être activés si les documents ne le sont pas (le contrôle est refait côté serveur).
- Durée : 30 jours par défaut, 1 à 365 jours, prolongation plafonnée à 365 jours.

### 2.3 Aucune écriture publique

Les routes publiques sont **strictement GET**. Il n’existe aucune route publique en POST, donc
aucun CSRF possible et aucune modification de données par un visiteur. Les seules écritures
déclenchées par une requête publique sont internes au module : une ligne de limitation de débit
et une ligne de journal de consultation.

---

## 3. Contrôles en place

| # | Contrôle | Emplacement |
|---|---|---|
| 1 | Jeton de 256 bits, jamais stocké ni journalisé en clair | `PortailService::newToken()` / `hashToken()`, `PortalController::store()` |
| 2 | Affichage du lien **une seule fois**, via un message flash | `PortalAdminController::store()` |
| 3 | Empreinte en base, recherche indexée, comparaison `hash_equals` | `PortailRepository::findByHash()`, `PortailService::decide()` |
| 4 | Format du jeton vérifié avant toute requête SQL | `PortailService::isTokenFormat()` |
| 5 | Expiration et révocation vérifiées **à chaque requête** | `PortailService::state()`, `decide()` |
| 6 | Limitation de débit par adresse : 240 requêtes / 10 min, 20 refus / 10 min → 429 + `Retry-After` | `PortalController::resolve()`, `PortailRepository::countAttempts()` |
| 7 | Adresse IP jamais stockée en clair (empreinte salée par `app.key`) | `PortailService::ipHash()` |
| 8 | Purge opportuniste de la table de tentatives | `PortailRepository::pruneAttempts()` |
| 9 | Périmètre vérifié à chaque requête, y compris pour les photos | `PortalController::photo()`, `PortailRepository::projectBelongsToClient()` |
| 10 | Photos servies par `ProjectImageStorage::read()` : chemin réel, MIME réel, dimensions, `nosniff` | `PortalController::photo()` |
| 11 | Aucune ressource externe, aucun script : CSP `default-src 'none'` | `PortalController::secure()` |
| 12 | `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet` + `<meta name="robots">` | `PortalController::secure()`, `views/partials/head.php` |
| 13 | `Referrer-Policy: no-referrer` (le jeton ne fuit pas vers un tiers) | `PortalController::secure()` |
| 14 | `Cache-Control: private, no-store` + `Pragma: no-cache` | `PortalController::secure()` |
| 15 | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `form-action 'none'` | `PortalController::secure()` |
| 16 | Clicjacking, injection HTML : toutes les sorties passent par `e()` | vues `portal.php`, `notice.php`, `partials/*` |
| 17 | Requêtes préparées, `IN` à placeholders, `LIMIT`/`INTERVAL` bornés en entier | `PortailRepository` |
| 18 | Écrans du cabinet protégés par session, CSRF et permissions du noyau | `Module::routes()`, `PortalAdminController` |
| 19 | Journal d’audit sans jeton : seuls l’identifiant et la portée sont enregistrés | `PortalAdminController` |
| 20 | Langue limitée aux locales supportées (`fr`, `ar`) | `PortalController::applyLocale()` |

### Vérifications exécutées

- 76 vérifications de logique (format et unicité des jetons, empreinte stable, comparaison,
  expiration, révocation, décision d’accès, bornes de durée, portée/ droits, avancement pondéré,
  synthèse, parité FR/AR des 164 clés, manifeste).
- 14 rendus (écran du cabinet FR/AR, vide, erreurs, lecture seule ; espace public FR/AR, portée
  client, montants masqués, vide ; pages d’état invalide / expiré / révoqué / débit dépassé) :
  aucune erreur, aucune clé de traduction brute.
- Contrôle statique : aucune balise `script`, aucun gestionnaire `on*`, aucun `<form>` dans les
  vues publiques ; aucune sortie non échappée (`<?= $…`) dans les vues du module.

---

## 4. Risques résiduels et recommandations noyau

| Risque | Niveau | Mitigation actuelle | Recommandation |
|---|---|---|---|
| Le noyau démarre une session pour **toute** requête, y compris publique : un simple GET crée un fichier de session | Moyen | Aucune donnée sensible dans la session ; cookie `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS | **P7** : rendre les routes marquées publiques sans session (`Application::boot()` avant routage) |
| Le lien circule par messagerie ou courriel et peut être transféré | Élevé (par nature) | Expiration courte possible, révocation, portée minimale, journal des consultations | Rappeler au cabinet un canal sûr ; privilégier 30 jours et un lien par projet |
| Les journaux HTTP du serveur web contiennent l’URL, donc le jeton | Moyen | Rien côté applicatif (le module ne journalise jamais l’URL) | Purger/limiter la rétention des `access_log`, ou **P8** : jeton en fragment/échange de cookie |
| Pas d’accès aux PDF : les documents sont listés sans téléchargement | Faible | Mention explicite dans l’écran | **P9** : route publique de PDF signée à courte durée, après revue dédiée |
| Photos : `ProjectImageStorage` suppose le stockage privé intact | Faible | Validation par le noyau (chemin, MIME, dimensions) | Vérifier les permissions `storage/` au déploiement |
| Pas de limitation par jeton, seulement par adresse | Faible | 240 requêtes / 10 min par adresse | Suffisant pour un usage cabinet ; à revoir en cas de mutualisation |

---

## 5. Exploitation : liste de contrôle

1. Créer un lien **par projet** et par destinataire ; éviter les liens « tous les projets ».
2. Ne cocher « montants » que si le client doit voir les prix des documents émis.
3. Transmettre le lien par un canal sûr, immédiatement après sa création (il n’est plus affiché
   ensuite).
4. Vérifier le journal des consultations lors de la clôture d’un dossier.
5. Révoquer dès la fin de la mission ; supprimer le lien si le journal ne présente plus d’intérêt.
6. Contrôler trimestriellement les liens actifs et leur date d’expiration.
7. Servir l’application en HTTPS : sans HTTPS, le jeton circule en clair sur le réseau.

---

## 6. Hors périmètre

- Création de compte client, authentification par mot de passe, récupération d’accès.
- Signature électronique, dépôt de pièces, messagerie ou commentaires côté client.
- Téléchargement des PDF et des pièces jointes.
- Toute écriture dans les tables du noyau : le module n’en fait aucune.
