-- Suppression des fiches clients et projets : réservée à l’administrateur (joker « * ») et à
-- l’architecte. Les autres rôles énumèrent leurs capacités une à une et n’héritent donc jamais
-- de ce droit par surprise. Script rejouable : la permission n’est ajoutée qu’une fois.
UPDATE roles
SET permissions = JSON_ARRAY_APPEND(permissions, '$', 'clients.delete')
WHERE name = 'architect'
  AND permissions IS NOT NULL
  AND JSON_CONTAINS(permissions, '"clients.delete"') = 0;

UPDATE roles
SET permissions = JSON_ARRAY_APPEND(permissions, '$', 'projects.delete')
WHERE name = 'architect'
  AND permissions IS NOT NULL
  AND JSON_CONTAINS(permissions, '"projects.delete"') = 0;
