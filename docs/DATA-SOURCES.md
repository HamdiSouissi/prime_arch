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

Le seed `database/seeds/postal_codes.php` contient un jeu de données adapté depuis **TuniPost** :

- Dépôt : <https://github.com/walidsa3d/tunipost>
- Fichier : `tunipost/codes.csv`
- Révision de la dernière modification identifiée du CSV : [`6578b92af6b73b935d3f09f875d00bb442652137`](https://github.com/walidsa3d/tunipost/commit/6578b92af6b73b935d3f09f875d00bb442652137) (16 août 2015). Le dépôt lui-même a été poussé pour la dernière fois en septembre 2015.
- SHA-256 du CSV à cette révision : `f9f849164eb7f961e5c9d1f185c416c4139b225a0ac3bc68647514c108015267`.
- Licence publiée avec le projet : **MIT**. L'avis de licence et de copyright est conservé dans `docs/licenses/TUNIPOST-MIT.txt`.
- Adaptation : suppression de six doublons exacts, conservation des libellés latins de localité et des noms de délégation source, ajout de la référence au gouvernorat Prime Arch, puis rattachement contrôlé aux codes de délégation existants.

Le CSV source contient 4 859 lignes. Le seed conserve **4 853 enregistrements distincts**, couvrant les 24 gouvernorats, 262 paires gouvernorat/délégation indiquées par la source et **970 valeurs de code postal distinctes**. Les codes sont stockés comme chaînes de quatre chiffres afin de préserver les zéros initiaux. Le CSV ne fournit pas de libellé arabe ; `locality_ar` est donc laissé vide plutôt que traduit ou inventé.

Le rapprochement avec le référentiel Prime Arch rattache 257 des 262 groupes source à une délégation après correspondance exacte ou alias contrôlé, sans collision. Cinq groupes restent volontairement sans `delegation_id` : `JENDOUBA/JENDOUBA`, `KEBILI/DOUZ`, `KEF/TOUIREF`, `SFAX/SFAX EST` et `SOUSSE/SOUSSE`. Leur `governorate_id` et leur libellé source `source_delegation` sont conservés. Sept délégations actuelles restent également sans groupe source rattaché : Jendouba Sud, Sousse Sidi Abdelhamid, Zaouia Ksiba Thraya, Sfax Ouest, Thyna, Douz Nord et Douz Sud. Cela reflète notamment des libellés/découpages différents ; aucun code n'est réparti ou inventé pour combler ces écarts.

**Limite importante :** TuniPost ne documente pas l'origine institutionnelle du CSV, ne fournit pas de date de vérification par La Poste tunisienne et n'affirme pas une conformité administrative actuelle. Le fichier est ancien (2015). Malgré la couverture annoncée par la source, Prime Arch ne peut donc pas garantir que cette liste soit exhaustive, encore à jour ou officiellement validée. Les suggestions de l'interface sont informatives ; confirmer le code auprès de La Poste tunisienne avant un usage réglementaire ou opérationnel sensible. Les sources sans licence identifiable n'ont pas été importées.
