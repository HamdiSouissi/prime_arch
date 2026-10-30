# Sources des données géographiques

## Gouvernorats

Les 24 gouvernorats bilingues sont préchargés dans `database/seeds/governorates.php`. Les codes (`TN-11`, etc.) suivent le référentiel déjà utilisé par Prime Arch.

## Délégations

La liste des 264 délégations est adaptée depuis le jeu **Tunisia Administrative Divisions** :

- Dépôt : <https://github.com/open-admin-data/tunisia-administrative-divisions>
- Fichier source : `data/all-delegation.json`
- Licence indiquée par le dépôt : **CC BY 4.0** (<https://creativecommons.org/licenses/by/4.0/>).
- Attribution : « Tunisia Administrative Divisions », open-admin-data, CC BY 4.0.
- Adaptation : conservation de l'identifiant de la délégation et des noms latins (`name.en`) et arabes (`name.local`), puis rattachement aux codes ISO des gouvernorats déjà présents dans Prime Arch. Les régions, secteurs et coordonnées n'ont pas été copiés.
- Version source consultée : 30 octobre 2026 ; le README de la source indique une mise à jour du jeu au 8 septembre 2026.

Les noms latins sont des graphies toponymiques, pas une validation administrative par l'État tunisien. Ils doivent être vérifiés avant toute utilisation réglementaire.

## Codes postaux

Le schéma `postal_codes` et les filtres de format à quatre chiffres sont en place, mais `database/seeds/postal_codes.php` est volontairement vide. La source de délégations ne contient aucun code postal et aucun jeu de données de codes postaux avec provenance/licence suffisamment claire n'a été retenu. Les codes postaux ne sont pas inventés : une source officielle ou autorisée doit être validée avant leur chargement.
