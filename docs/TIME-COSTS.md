# Temps, coûts et avancement

## Données et calculs

- `user_hourly_rates` conserve un coût horaire interne par utilisateur et date d’effet. Les jalons sont ajoutés, jamais remplacés ; une même personne ne peut avoir deux taux à la même date.
- `time_entries` associe chaque saisie à son auteur, son projet et une phase du projet. La durée est stockée en `DECIMAL(6,2)` et saisie par tranches de quinze minutes, jusqu’à 24 heures par entrée.
- Au moment de l’enregistrement, le taux valable à la date de travail et le coût calculé sont copiés dans la saisie. Le calcul SQL décimal est `ROUND(durée × taux horaire, 3)` ; aucune opération monétaire métier ne passe par un `float` PHP. Le coût d’une saisie déjà enregistrée ne change pas lorsqu’un nouveau taux prend effet.
- Le budget d’heures du projet et les budgets par phase sont indépendants. Les poids des phases doivent totaliser exactement `100,00 %`. L’avancement global est la moyenne pondérée `Σ(poids × progression) / Σ(poids)`.
- La marge affichée est une estimation interne : `honoraires prévus − coûts internes constatés`. Le pourcentage est rapporté aux honoraires prévus. Ce n’est ni un chiffre de facturation, ni un résultat comptable ou fiscal.
- Les honoraires du projet sont ventilés sur les phases en millimes par la méthode du plus fort reste : la somme des allocations par phase reste exactement égale au budget d’honoraires, même lorsque les arrondis individuels seraient ambigus.

Les honoraires prévus, taux, coûts et marges sont stockés/calculés en `DECIMAL` à trois décimales. Les vues ne les exposent qu’aux permissions financières ; les chaînes décimales PDO sont formatées sans conversion flottante.

## Permissions

- **Administrateur** : toutes les permissions, dont `finance.costs.manage` et `finance.rates.manage`.
- **Architecte** : gestion des saisies via `time.*`, mais aucun accès financier implicite. Le joker `time.*` ne couvre jamais `finance.*`.
- **Collaborateur** : consultation et gestion de ses propres saisies (`time.view`, `time.manage`).
- **Lecture seule** : consultation de ses propres saisies (`time.view`).
- **Comptable** : accès en lecture aux coûts/rentabilité et à l’historique des taux (`finance.costs.view`, `finance.rates.view`), sans droit de modifier les projets ou les taux.

Les contrôles sont effectués côté serveur sur les routes et dans la portée des requêtes. Les taux sont consultables sur `/settings/hourly-rates`; leur ajout demande `finance.rates.manage`. Le rôle de Comptable reçoit `projects.view` pour consulter les fiches et indicateurs associés.

## Mise en service

Après déploiement du code, appliquer les migrations dans l’ordre puis synchroniser les rôles intégrés. La graine attribue aussi des poids égaux aux phases des projets existants qui n’en ont pas encore :

```bash
php bin/migrate.php
php bin/seed.php
```

La migration `0008_time_costs_progress.sql` dépend des tables de projets/phases créées par `0007_create_clients_projects.sql`. Si les migrations `0006` ou `0007` sont toujours en échec, corriger et confirmer leur succès avant de considérer cette phase opérationnelle. Après l’initialisation, un administrateur doit saisir un taux historique pour chaque utilisateur avant sa première saisie de temps.
