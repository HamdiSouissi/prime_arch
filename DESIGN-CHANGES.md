# Inventaire du livrable Prime Arch

Chaque entrée est incluse dans `prime-arch-design.zip` à son chemin de projet exact. Le ZIP peut être extrait par-dessus le dépôt existant.

## Fichiers ajoutés (A)

- **A — `DESIGN-CHANGES.md`** : inventaire des fichiers ajoutés et modifiés dans ce livrable.
- **A — `app/Controllers/DesignController.php`** : contrôleur des aperçus statiques, fictifs et DEV ONLY.
- **A — `app/Views/layouts/auth.php`** : layout clair et responsive de l’écran de connexion.
- **A — `app/Views/pages/auth/login.php`** : formulaire statique de connexion avec protection CSRF.
- **A — `app/Views/pages/clients/index.php`** : répertoire clients avec recherche, filtres, statuts et tableau.
- **A — `app/Views/pages/clients/show.php`** : fiche client, coordonnées, projets et documents récents.
- **A — `app/Views/pages/dashboard.php`** : tableau de bord avec statistiques, projets, temps, échéances et activité.
- **A — `app/Views/pages/documents/index.php`** : liste filtrable de devis, contrats et factures.
- **A — `app/Views/pages/documents/show.php`** : aperçu de devis avec TVA, timbre, retenue, TTC et actions PDF.
- **A — `app/Views/pages/projects/index.php`** : portefeuille de projets avec filtres et affichage grille/liste.
- **A — `app/Views/pages/projects/show.php`** : fiche projet, phases, onglets, coûts et champs personnalisés.
- **A — `app/Views/pages/settings/index.php`** : réglages du cabinet, fiscalité, utilisateurs et modules.
- **A — `app/Views/pages/settings/modules.php`** : catalogue d’extensions, interrupteurs et zone ZIP de démonstration.
- **A — `app/Views/pages/styleguide.php`** : bibliothèque interactive des tokens et composants UI.
- **A — `app/Views/pages/time/index.php`** : chronomètre, récapitulatif hebdomadaire et saisies par phase.
- **A — `app/Views/partials/badge.php`** : badge de statut paramétrable avec teinte sémantique.
- **A — `app/Views/partials/command-palette.php`** : palette globale filtrable, accessible au clavier.
- **A — `app/Views/partials/empty-state.php`** : état vide illustré et paramétrable.
- **A — `app/Views/partials/page-header.php`** : en-tête de page avec titre large, sous-titre et actions.
- **A — `app/Views/partials/progress.php`** : progression accessible avec valeur ARIA et teinte optionnelle.
- **A — `app/Views/partials/quick-add.php`** : sheet d’action rapide pour les destinations de démonstration.
- **A — `app/Views/partials/stat-card.php`** : carte de statistique paramétrable.
- **A — `app/Views/partials/tabbar.php`** : navigation mobile inférieure à six destinations.
- **A — `app/Views/partials/toast.php`** : région de notification accessible et réutilisable.
- **A — `app/Views/pdf/base.php`** : base mPDF avec en-tête, destinataire, mentions et pagination.
- **A — `app/Views/pdf/contract.php`** : modèle de contrat de maîtrise d’œuvre.
- **A — `app/Views/pdf/invoice.php`** : modèle de facture avec totaux fiscaux indicatifs.
- **A — `app/Views/pdf/quote.php`** : modèle de devis avec lignes, TVA, timbre et net à payer.
- **A — `docs/DESIGN-SYSTEM.md`** : documentation des tokens, composants, accessibilité et extensions.
- **A — `public/assets/css/base.css`** : fond mesh, typographie, focus et préférences d’accessibilité.
- **A — `public/assets/css/components.css`** : composants réutilisables et contrôles interactifs.
- **A — `public/assets/css/layout.css`** : shell responsive, navigation vitrée et barre mobile.
- **A — `public/assets/css/pages.css`** : compositions des écrans et règles responsive dédiées.
- **A — `public/assets/css/pdf.css`** : styles mPDF sobres, imprimables et compatibles RTL.
- **A — `public/assets/css/print.css`** : feuille d’impression allégée du chrome applicatif.
- **A — `public/assets/css/tokens.css`** : palette claire, typographie, rayons, espacements et ombres.
- **A — `public/assets/img/empty-clients.svg`** : illustration vectorielle d’un état client vide.
- **A — `public/assets/img/empty-documents.svg`** : illustration vectorielle d’un état document vide.
- **A — `public/assets/img/empty-projects.svg`** : illustration vectorielle d’un état projet vide.
- **A — `public/assets/img/empty-search.svg`** : illustration vectorielle sans résultat de recherche.
- **A — `public/assets/img/empty-tasks.svg`** : illustration vectorielle d’une liste de tâches vide.
- **A — `public/assets/img/icons.svg`** : sprite SVG de 61 icônes au trait arrondi.
- **A — `public/assets/img/logo-placeholder.svg`** : marque vectorielle de remplacement pour le cabinet.
- **A — `public/assets/js/ui.js`** : interactions vanilla pour dialogues, palette, onglets, filtres, toasts et menu.

## Fichiers modifiés (M)

- **M — `app/Views/layouts/app.php`** : layout principal clair, composants globaux, navigation et indication DEV ONLY.
- **M — `app/Views/partials/sidebar.php`** : navigation principale à six entrées, états actifs et sprite d’icônes.
- **M — `app/Views/partials/topbar.php`** : recherche globale, raccourci clavier et accès aux actions rapides.
- **M — `config/routes.php`** : routes GET `/design/*`, conditionnées par `APP_ENV` et désactivées en production.
- **M — `public/assets/css/app.css`** : point d’entrée en couches CSS qui importe les feuilles du système.
- **M — `public/assets/js/app.js`** : initialisation du module UI sans dépendance externe.
