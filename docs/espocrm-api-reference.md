# EspoCRM — Référence API REST

---

## Authentification

Toutes les requêtes (sauf `noAuth: true`) doivent porter un header d'authentification.

### Basic Auth (username + password)

```text
Authorization: Basic base64(username:password)
```

**PowerShell :**

```powershell
$creds = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("admin:motdepasse"))
$headers = @{ Authorization = "Basic $creds" }
Invoke-WebRequest -Uri "http://localhost/api/v1/Contact" -Headers $headers
```

**curl :**

```bash
curl -u admin:motdepasse http://localhost/api/v1/Contact
```

### API Key (pour intégrations)

```text
X-Api-Key: VOTRE_CLE_API
```

La clé API se génère dans EspoCRM → Admin → API Users.

### Token Auth (sessions)

Après login :

```text
Espo-Authorization: base64(username:token)
```

Le token est retourné dans `X-Auth-Token` à chaque réponse authentifiée.
Il expire si inactif (configurable).

---

## Format des réponses

Toutes les réponses sont en JSON.

**Liste :**

```json
{
  "total": 42,
  "list": [
    { "id": "abc123", "name": "Rakoto Tech SA", ... },
    ...
  ]
}
```

**Enregistrement unique :**

```json
{
  "id": "abc123",
  "name": "Rakoto Tech SA",
  "type": "Customer",
  "industry": "Technology",
  "assignedUserId": "user1",
  "assignedUserName": "Admin",
  "createdAt": "2026-01-15 09:00:00",
  "modifiedAt": "2026-04-01 14:30:00"
}
```

**Erreurs :**

```json
{ "message": "Not found", "code": 404 }
{ "message": "Forbidden", "code": 403 }
{ "message": "Validation error", "code": 422, "validation": {"name": "required"} }
```

---

## CRUD générique — Routes `:controller`

Ces routes existent pour **toutes les entités** : Contact, Account, Lead, Opportunity,
Task, Meeting, Call, Case, Document, Campaign, KnowledgeBaseArticle, User...

### Lister

```http
GET /api/v1/:Entity
```

**Paramètres de query string :**

| Paramètre      | Type        | Description                            |
|----------------|-------------|----------------------------------------|
| `maxSize`      | int         | Nombre max de résultats (défaut : 20)  |
| `offset`       | int         | Décalage pour pagination               |
| `orderBy`      | string      | Champ de tri (ex: `name`, `createdAt`) |
| `order`        | string      | `asc` ou `desc`                        |
| `where`        | JSON encodé | Filtres (voir section Filtres)         |
| `searchParams` | JSON encodé | Paramètres de recherche avancée        |
| `select`       | string      | Liste de champs séparés par virgule    |

**Exemple :**

```bash
GET /api/v1/Contact?maxSize=10&offset=0&orderBy=lastName&order=asc
GET /api/v1/Opportunity?where=[{"type":"equals","attribute":"stage","value":"Prospecting"}]
```

### Lire un enregistrement

```http
GET /api/v1/:Entity/:id
```

```bash
GET /api/v1/Contact/abc123def456
```

### Créer

```http
POST /api/v1/:Entity
Content-Type: application/json

{ "firstName": "Jean", "lastName": "Rakoto", "emailAddress": "jean@example.mg" }
```

Retourne l'enregistrement créé avec son `id`.

### Modifier

```http
PUT /api/v1/:Entity/:id
Content-Type: application/json

{ "status": "Active", "title": "Directeur Général" }
```

Retourne l'enregistrement mis à jour.

### Supprimer

```http
DELETE /api/v1/:Entity/:id
```

Retourne `true` si succès (soft-delete — l'enregistrement reste en BDD avec `deleted = 1`).

---

## Relations entre entités

### Lister les enregistrements liés

```http
GET /api/v1/:Entity/:id/:relation
```

```bash
# Contacts liés à un Account
GET /api/v1/Account/abc123/contacts

# Opportunities liées à un Contact
GET /api/v1/Contact/abc123/opportunities

# Activités liées à un Account
GET /api/v1/Account/abc123/meetings
GET /api/v1/Account/abc123/calls
GET /api/v1/Account/abc123/tasks
```

### Créer une relation (lier)

```http
POST /api/v1/:Entity/:id/:relation
Content-Type: application/json

{ "id": "idDeLentiteALier" }
```

### Supprimer une relation (délier)

```http
DELETE /api/v1/:Entity/:id/:relation/:relatedId
```

---

## Endpoints spécifiques

### Application

```http
GET /api/v1/App/user           # Profil utilisateur courant + permissions
GET /api/v1/App/about          # Version EspoCRM
GET /api/v1/App/appParams      # Paramètres applicatifs frontend
POST /api/v1/App/destroyAuthToken  # Déconnexion (invalide le token)
```

### Métadonnées et configuration

```http
GET /api/v1/Metadata           # Définitions complètes de toutes les entités
GET /api/v1/Settings           # Configuration publique de l'application
PATCH /api/v1/Settings         # Modifier la configuration (admin only)
GET /api/v1/I18n               # Traductions (ne nécessite pas d'auth)
```

### Stream (fil d'activité)

```http
GET /api/v1/Stream             # Stream de l'utilisateur courant
GET /api/v1/GlobalStream       # Stream global (toutes activités)
GET /api/v1/:Entity/:id/stream # Stream d'un enregistrement spécifique
POST /api/v1/:Entity/:id/subscription  # S'abonner au stream d'un enregistrement
DELETE /api/v1/:Entity/:id/subscription  # Se désabonner
```

### Activités (Calendar)

```http
GET /api/v1/Activities                              # Calendrier (events du jour/semaine)
GET /api/v1/Activities/upcoming                     # Activités à venir
GET /api/v1/Activities/:parentType/:id/:type        # Activités liées à une entité
GET /api/v1/Timeline                                # Timeline étendue
GET /api/v1/Timeline/busyRanges                     # Plages occupées (pour scheduling)
GET /api/v1/Meeting/:id/attendees                   # Participants d'une réunion
GET /api/v1/Call/:id/attendees                      # Participants d'un appel
```

### Lead — Conversion

```http
POST /api/v1/Lead/:id/action/convert
Content-Type: application/json

{
  "account": { "id": null, "name": "Nouvelle Société" },
  "contact": { "id": null },
  "opportunity": { "id": null, "name": "Première opportunité", "amount": 5000, "closeDate": "2026-12-31", "stage": "Prospecting" }
}
```

### Email

```http
GET /api/v1/Email                    # Liste des emails
POST /api/v1/Email                   # Envoyer un email
GET /api/v1/Email/:id                # Lire un email
POST /api/v1/Email/:id/action/send   # Envoyer un brouillon
POST /api/v1/Email/action/getDraft   # Créer un brouillon
```

### Import CSV

```http
POST /api/v1/Import/file             # Upload du fichier CSV → retourne attachmentId
POST /api/v1/Import                  # Lancer l'import avec params + mapping
GET /api/v1/Import                   # Historique des imports
GET /api/v1/Import/:id               # Détail d'un import
POST /api/v1/Import/:id/revert       # Annuler un import (supprime les enregistrements créés)
POST /api/v1/Import/:id/removeDuplicates  # Supprimer les doublons détectés
```

### Export

```http
POST /api/v1/MassAction
Content-Type: application/json

{
  "action": "export",
  "entityType": "Contact",
  "params": {
    "fieldList": ["firstName", "lastName", "emailAddress"],
    "format": "csv"
  }
}
```

### Recherche globale

```http
GET /api/v1/GlobalSearch?q=rakoto&maxSize=10
```

Retourne des résultats de toutes les entités (Contact, Account, Lead...).

### Layout (vues)

```http
GET /api/v1/:Entity/layout/:layoutType   # Récupérer un layout
PUT /api/v1/:Entity/layout/:layoutType   # Modifier un layout (admin)
```

Types de layout : `list`, `detail`, `edit`, `detailSmall`, `relationships`, `listRelated`.

### Admin

```http
POST /api/v1/Admin/rebuild        # Rebuild cache + assets
POST /api/v1/Admin/clearCache     # Vider le cache
GET /api/v1/Admin/jobs            # Jobs en attente/en cours
GET /api/v1/Admin/fieldManager/:entity/:field  # Info sur un champ
POST /api/v1/Admin/fieldManager/:entity        # Créer un champ custom
```

### Lead Capture (formulaires web)

```http
POST /api/v1/LeadCapture/:apiKey        # Soumettre un lead depuis un formulaire web
POST /api/v1/LeadCapture/form/:formId   # Soumettre via formulaire EspoCRM
```

Ces routes ne nécessitent pas d'authentification (`noAuth: true`).

### Notifications

```http
GET /api/v1/Notification               # Liste des notifications
PUT /api/v1/Notification/:id           # Marquer comme lue
POST /api/v1/Notification/action/markAllRead  # Tout marquer comme lu
```

---

## Filtres et recherche

Les filtres sont passés en `where` (JSON array encodé) ou via `searchParams`.

**Format d'un filtre :**

```json
[
  { "type": "equals", "attribute": "stage", "value": "Prospecting" },
  { "type": "contains", "attribute": "name", "value": "rakoto" },
  { "type": "greaterThan", "attribute": "amount", "value": 10000 },
  { "type": "isNotNull", "attribute": "accountId" },
  { "type": "before", "attribute": "closeDate", "value": "2026-12-31" }
]
```

**Types de filtre courants :**

| Type                                     | Description                |
|------------------------------------------|----------------------------|
| `equals`                                 | Égalité exacte             |
| `notEquals`                              | Différent                  |
| `contains`                               | Contient (LIKE %val%)      |
| `startsWith`                             | Commence par               |
| `isNull` / `isNotNull`                   | Champ vide / non vide      |
| `greaterThan` / `lessThan`               | Comparaison numérique      |
| `between`                                | Entre deux valeurs         |
| `before` / `after`                       | Comparaison de dates       |
| `today` / `currentWeek` / `currentMonth` | Filtres de dates relatives |
| `in` / `notIn`                           | Dans une liste de valeurs  |

---

## Pagination

```http
GET /api/v1/Contact?offset=0&maxSize=20
GET /api/v1/Contact?offset=20&maxSize=20  # Page 2
```

La réponse inclut toujours `"total"` (nombre total d'enregistrements) permettant
de calculer le nombre de pages : `Math.ceil(total / maxSize)`.

---

## Codes HTTP

| Code  | Signification                                     |
|-------|---------------------------------------------------|
| `200` | Succès                                            |
| `201` | Créé (POST)                                       |
| `400` | Requête invalide (mauvais JSON, champs invalides) |
| `401` | Non authentifié                                   |
| `403` | Accès refusé (ACL)                                |
| `404` | Enregistrement introuvable                        |
| `409` | Conflit (doublon détecté)                         |
| `500` | Erreur serveur                                    |

---

## Exemples complets PowerShell

```powershell
$base = "http://localhost/api/v1"
$creds = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("admin:admin"))
$h = @{ Authorization = "Basic $creds"; "Content-Type" = "application/json" }

# Créer un compte
$body = '{"name":"Rakoto Tech SA","type":"Customer","industry":"Technology"}'
Invoke-RestMethod -Uri "$base/Account" -Method Post -Headers $h -Body $body

# Lister les contacts avec filtre
$where = '[{"type":"contains","attribute":"lastName","value":"Rakoto"}]'
Invoke-RestMethod -Uri "$base/Contact?where=$where" -Headers $h

# Lire une opportunité
Invoke-RestMethod -Uri "$base/Opportunity/ID_ICI" -Headers $h

# Mettre à jour le stage
$body = '{"stage":"Closed Won"}'
Invoke-RestMethod -Uri "$base/Opportunity/ID_ICI" -Method Put -Headers $h -Body $body

# Supprimer un lead
Invoke-RestMethod -Uri "$base/Lead/ID_ICI" -Method Delete -Headers $h

# Lier un contact à un compte
$body = '{"id":"ID_CONTACT"}'
Invoke-RestMethod -Uri "$base/Account/ID_ACCOUNT/contacts" -Method Post -Headers $h -Body $body
```
