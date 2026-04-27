# EspoCRM — Modules et entités

EspoCRM est organisé en deux couches : le **core** (`application/Espo/`) et le **module CRM**
(`application/Espo/Modules/Crm/`). Les entités CRM sont définies dans le module Crm ;
les entités système (User, Role, Email...) sont dans le core.

---

## Module CRM — entités principales

### Account (Compte)

Représente une entreprise ou organisation. C'est le pivot central : Contacts, Opportunités,
Leads, Cases, Documents et Activités peuvent tous être liés à un Account.

**Champs clés :**

| Champ             | Type                | Description                                               |
|-------------------|---------------------|-----------------------------------------------------------|
| `name`            | varchar             | Nom de l'entreprise                                       |
| `website`         | url                 | Site web                                                  |
| `emailAddress`    | email               | Email principal                                           |
| `phoneNumber`     | phone               | Téléphone                                                 |
| `type`            | enum                | Customer / Partner / Investor / Reseller / Vendor / Other |
| `industry`        | enum                | Technology / Finance / Healthcare / Agriculture...        |
| `billingAddress`  | address             | Adresse de facturation                                    |
| `shippingAddress` | address             | Adresse de livraison                                      |
| `description`     | text                | Notes libres                                              |
| `assignedUser`    | link → User         | Responsable commercial                                    |
| `teams`           | linkMultiple → Team | Équipes ayant accès                                       |

**Relations :**

- 1 Account → N Contacts
- 1 Account → N Opportunities
- 1 Account → N Cases
- 1 Account → N Meetings / Calls / Tasks
- 1 Account → N Documents

---

### Contact

Personne physique liée à un Account (ou indépendante).

**Champs clés :**

| Champ                    | Type           | Description                                |
|--------------------------|----------------|--------------------------------------------|
| `firstName` / `lastName` | varchar        | Prénom / Nom                               |
| `emailAddress`           | email          | Email                                      |
| `phoneNumber`            | phone          | Téléphone                                  |
| `title`                  | varchar        | Poste (Directeur, Comptable...)            |
| `accountName`            | varchar        | Nom du compte lié (résolu automatiquement) |
| `account`                | link → Account | Relation directe                           |
| `doNotCall`              | bool           | Ne pas appeler                             |
| `description`            | text           | Notes                                      |
| `assignedUser`           | link → User    | Responsable                                |

**Relations :**

- N Contacts → 1 Account
- 1 Contact → N Opportunities (via `contacts` linkMultiple)
- 1 Contact → N Cases
- Peut être converti depuis un Lead

---

### Lead

Prospect non encore qualifié — n'a pas encore de compte associé.
Une fois qualifié, il peut être **converti** en Contact + Account + Opportunity.

**Statuts :** `New` → `Assigned` → `In Process` → `Converted` | `Recycled` | `Dead`

**Champs clés :**

| Champ                          | Type        | Description                                               |
|--------------------------------|-------------|-----------------------------------------------------------|
| `firstName` / `lastName`       | varchar     | Identité                                                  |
| `emailAddress` / `phoneNumber` | email/phone | Coordonnées                                               |
| `status`                       | enum        | New / Assigned / In Process / Converted / Recycled / Dead |
| `source`                       | enum        | Web Site / Call / Email / Partner / Campaign...           |
| `industry`                     | enum        | Secteur d'activité                                        |
| `accountName`                  | varchar     | Nom de l'entreprise (texte libre)                         |
| `title`                        | varchar     | Poste                                                     |
| `website`                      | url         | Site web de la société                                    |
| `convertedAt`                  | datetime    | Date de conversion                                        |

**Action spéciale — Conversion :**
`POST /api/v1/Lead/{id}/action/convert` — crée Contact, Account et Opportunity à partir du Lead.

---

### Opportunity (Opportunité)

Deal commercial en cours. Suit un pipeline de vente par stages.

**Stages :** `Prospecting` → `Qualification` → `Needs Analysis` → `Value Proposition`
→ `Id. Decision Makers` → `Perception Analysis` → `Proposal/Price Quote`
→ `Negotiation/Review` → `Closed Won` | `Closed Lost`

**Champs clés :**

| Champ          | Type                   | Description                      |
|----------------|------------------------|----------------------------------|
| `name`         | varchar                | Nom du deal                      |
| `amount`       | currency               | Montant                          |
| `probability`  | int                    | Probabilité de closing (%)       |
| `stage`        | enum                   | Étape du pipeline                |
| `closeDate`    | date                   | Date de closing prévue           |
| `leadSource`   | enum                   | Source (Web, Email, Campaign...) |
| `account`      | link → Account         | Compte associé                   |
| `contacts`     | linkMultiple → Contact | Contacts concernés               |
| `assignedUser` | link → User            | Responsable                      |

**Calcul automatique :** `amountWeightedConverted = amount × probability / 100`

---

### Case (Dossier support)

Ticket de support client.

**Statuts :** `New` → `Assigned` → `Pending` → `Closed` | `Rejected`
**Priorités :** `Low` / `Normal` / `High` / `Urgent`

**Champs clés :**

| Champ          | Type           | Description                           |
|----------------|----------------|---------------------------------------|
| `name`         | varchar        | Sujet du ticket                       |
| `number`       | autoincrement  | Numéro unique (auto)                  |
| `status`       | enum           | Statut                                |
| `priority`     | enum           | Priorité                              |
| `type`         | enum           | User Problem / Incident / Question... |
| `description`  | text           | Description                           |
| `account`      | link → Account | Client                                |
| `contact`      | link → Contact | Contact                               |
| `assignedUser` | link → User    | Agent support                         |

---

### Meeting (Réunion)

Événement planifié avec plusieurs participants.

**Statuts :** `Planned` / `Held` / `Not Held`

**Champs clés :**

| Champ         | Type                   | Description               |
|---------------|------------------------|---------------------------|
| `name`        | varchar                | Sujet                     |
| `status`      | enum                   | Planned / Held / Not Held |
| `dateStart`   | datetime               | Début                     |
| `dateEnd`     | datetime               | Fin                       |
| `duration`    | int                    | Durée en minutes          |
| `location`    | varchar                | Lieu                      |
| `description` | text                   | Description               |
| `users`       | linkMultiple → User    | Participants internes     |
| `contacts`    | linkMultiple → Contact | Contacts invités          |
| `leads`       | linkMultiple → Lead    | Leads invités             |

---

### Call (Appel téléphonique)

**Directions :** `Inbound` / `Outbound`
**Statuts :** `Planned` / `Held` / `Not Held`

**Champs clés :**

| Champ         | Type                   | Description               |
|---------------|------------------------|---------------------------|
| `name`        | varchar                | Sujet de l'appel          |
| `status`      | enum                   | Planned / Held / Not Held |
| `direction`   | enum                   | Inbound / Outbound        |
| `dateStart`   | datetime               | Date/heure                |
| `duration`    | int                    | Durée en secondes         |
| `description` | text                   | Notes                     |
| `users`       | linkMultiple → User    | Participants              |
| `contacts`    | linkMultiple → Contact | Contacts                  |

---

### Task (Tâche)

Action à faire, associable à n'importe quelle entité parente.

**Statuts :** `Not Started` / `Started` / `Completed` / `Canceled` / `Deferred`
**Priorités :** `Low` / `Normal` / `High` / `Urgent`

**Champs clés :**

| Champ                           | Type             | Description                                       |
|---------------------------------|------------------|---------------------------------------------------|
| `name`                          | varchar          | Titre                                             |
| `status`                        | enum             | Statut                                            |
| `priority`                      | enum             | Priorité                                          |
| `dateStart` / `dateEnd`         | datetimeOptional | Plage                                             |
| `dateStartDate` / `dateDueDate` | date             | Dates sans heure                                  |
| `parent`                        | linkParent       | Entité parente (Account, Contact, Opportunity...) |
| `contact`                       | link → Contact   | Contact lié                                       |
| `account`                       | link → Account   | Compte lié                                        |
| `assignedUser`                  | link → User      | Responsable                                       |

---

### Document

Fichier attaché, classable par dossier.

**Champs clés :** `name`, `status` (Active/Draft/Expired), `publishDate`, `expirationDate`,
`file` (attachment), `folder` (link → DocumentFolder), `accounts`, `contacts`, `opportunities`.

---

### Campaign (Campagne marketing)

Campagne de mailing/appel vers des listes cibles.

**Types :** `Email` / `Mail` / `Web` / `Phone` / `Internal`
**Statuts :** `Planning` / `Active` / `Inactive` / `Complete`

Lie des **TargetList** (listes de destinataires) contenant des Contacts, Leads, Users, Targets.
Génère des **CampaignLogRecord** pour tracer les envois, ouvertures, clics, désabonnements.

---

### KnowledgeBaseArticle

Article de base de connaissance, lié à des catégories hiérarchiques.
Statuts : `Draft` / `Published`. Consultable via l'interface et via les Cases.

---

## Module Core — entités système

| Entité                | Description                                                  |
|-----------------------|--------------------------------------------------------------|
| `User`                | Utilisateurs (Regular, Admin, Portal, API)                   |
| `Team`                | Équipes — contrôlent l'accès aux enregistrements             |
| `Role`                | Rôles ACL — définissent les permissions par entité et action |
| `Portal`              | Portail client — URL dédiée pour les utilisateurs externes   |
| `PortalRole`          | Rôles ACL spécifiques aux portails                           |
| `Email`               | Emails entrants/sortants, avec pièces jointes                |
| `EmailAccount`        | Comptes email personnels (IMAP/SMTP)                         |
| `InboundEmail`        | Boîtes email partagées (support, commercial...)              |
| `EmailTemplate`       | Modèles d'email avec variables `{Name}`, `{AccountName}`...  |
| `Notification`        | Notifications in-app (assignation, mention, stream...)       |
| `Note`                | Entrée de Stream (commentaire, changement, email entrant...) |
| `Attachment`          | Fichier joint — lié à n'importe quelle entité                |
| `Import`              | Historique des imports CSV                                   |
| `ScheduledJob`        | Tâches cron configurables depuis l'admin                     |
| `Webhook`             | Webhooks sortants déclenchés par des événements              |
| `DashboardTemplate`   | Modèles de tableau de bord partagés                          |
| `WorkingTimeCalendar` | Calendriers de travail (pour les SLA et disponibilités)      |

---

## Entités transverses

### Stream (Fil d'activité)

Chaque entité "streamable" (Account, Contact, Opportunity, Case...) a un fil d'activité.
Il contient des `Note` de plusieurs types :

| Type de Note     | Déclencheur                         |
|------------------|-------------------------------------|
| `Post`           | Commentaire manuel d'un utilisateur |
| `Update`         | Modification d'un champ audité      |
| `Create`         | Création de l'enregistrement        |
| `Assign`         | Changement du `assignedUser`        |
| `Status`         | Changement d'un champ de statut     |
| `Email Sent`     | Email envoyé lié à l'enregistrement |
| `Email Received` | Email reçu lié à l'enregistrement   |
| `Relate`         | Liaison avec une autre entité       |

### Reminder (Rappel)

Rappel associé à un Meeting ou une Task. Peut envoyer une notification email ou une
popup. Géré par le ScheduledJob `Reminder`.
