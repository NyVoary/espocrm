# EspoCRM — Flux de données

Ce document explique comment les données entrent, circulent et sortent du système.

---

## 1. Création d'un enregistrement — flux complet

Exemple : un commercial crée une Opportunity depuis l'interface.

```text
Utilisateur clique "Create"
    │
    ▼
SPA Backbone.js (browser)
    │  POST /api/v1/Opportunity
    │  { "name": "Deal Rakoto", "amount": 50000, "stage": "Prospecting", ... }
    ▼
nginx → PHP-FPM → Slim Router
    │
    ▼
Controllers/RecordBase::createAction()
    │
    ├── ACL check : acl->check('Opportunity', 'create') → 403 si refusé
    │
    ▼
Tools/Opportunity/Service.php (ou Services/Record.php)
    │
    ├── beforeCreate hook (Hooks/Opportunity/BeforeSave.php)
    │   → calcule amountWeightedConverted, valide closeDate > aujourd'hui
    │
    ├── Validation des champs requis (name, amount, stage, closeDate)
    │
    ├── EntityManager::createEntity('Opportunity', $data)
    │   ├── INSERT INTO opportunity (id, name, amount, stage, ...) VALUES (...)
    │   └── INSERT INTO entity_relation (opportunityId, accountId) si compte lié
    │
    ├── afterCreate hook
    │   ├── Crée une Note "Create" dans le Stream
    │   └── Envoie notification au assignedUser si différent du créateur
    │
    └── Retourne l'entité créée en JSON
```

---

## 2. Flux de l'email entrant

EspoCRM peut recevoir des emails et les lier automatiquement aux enregistrements.

```text
Email reçu sur boîte IMAP (ex: support@entreprise.mg)
    │
    ▼  (ScheduledJob "ProcessInboundEmails" — toutes les 3 min)
Tools/Email/InboxService.php
    │
    ├── Récupère les emails non lus via IMAP
    │
    ├── Pour chaque email :
    │   ├── Crée un enregistrement Email en BDD
    │   ├── Sauvegarde les pièces jointes en tant qu'Attachment
    │   │
    │   ├── Recherche de l'expéditeur dans les entités existantes :
    │   │   Email address → Contact ? Lead ? User ?
    │   │
    │   ├── Si trouvé → lie l'email à l'entité (via emailAddress matching)
    │   │
    │   ├── Si règle de routage configurée → assigne à un utilisateur / équipe
    │   │
    │   └── Peut créer un Case automatiquement (selon config InboundEmail)
    │
    └── Marque l'email comme lu sur IMAP
```

---

## 3. Flux de la campagne email

```text
Admin crée une Campaign + des TargetList
    │
    ▼
Campaign contient :
    ├── TargetList avec Contacts / Leads / Targets
    └── EmailTemplate

Admin crée un MassEmail lié à la Campaign
    │
    ▼
ScheduledJob "MassEmailSending" (toutes les 5 min)
    │
    ├── Pour chaque destinataire de la TargetList :
    │   ├── Vérifie optOut (si déjà désabonné → skip)
    │   ├── Crée un EmailQueueItem (statut Pending)
    │   └── Envoie l'email via SMTP
    │       ├── Remplace les variables {{name}}, {{accountName}}...
    │       └── Ajoute un lien de désabonnement (tracking URL)
    │
    ├── Crée un CampaignLogRecord par email envoyé :
    │   (Sent / Opened / Clicked / Opted Out / Bounced)
    │
    └── Stats disponibles dans Campaign → "Log"
```

---

## 4. Modèle relationnel — schéma des entités CRM

```text
User ─────────────── assignedUserId ──────────────────────────────────┐
                                                                       │
Account ──────────────────────────────────────────────────────────────┤
  │ accountId                                                          │
  ├──→ Contact ──────────────────────────────────────────────────── (tous)
  │      │ contactId
  │      └──→ Opportunity (via contact_opportunity_pivot)
  │
  ├──→ Opportunity ──────────────────────────────────────────────── (tous)
  │      │ opportunityId (stage, amount, closeDate...)
  │      └──→ Contacts (M:N via contact_opportunity)
  │
  ├──→ Case ─────────────────────────────────────────────────────── (tous)
  │
  ├──→ Meeting / Call / Task ─────────────────────────────────────── (tous)
  │      └── parentType / parentId (linkParent → Account, Contact, Opportunity...)
  │
  └──→ Document / Email ──────────────────────────────────────────── (tous)

Lead ──────────────────────────────────────────────────────────────── indépendant
  │ (après conversion)
  └──→ Contact + Account + Opportunity créés

Team ─── team_user_pivot ──→ User
  └── team_record_pivot ──→ n'importe quelle entité (contrôle accès "team")
```

---

## 5. Flux de l'ACL — comment les permissions sont appliquées

```text
Requête API : GET /api/v1/Opportunity
    │
    ▼
Acl::check('Opportunity', 'read')
    │
    ├── Récupère le Role de l'utilisateur courant
    ├── Lit la permission scope 'Opportunity' dans le Role
    │
    ├── Si 'no'   → 403 Forbidden immédiatement
    │
    ├── Si 'own'  → ajoute WHERE assignedUserId = currentUserId
    │
    ├── Si 'team' → ajoute WHERE (
    │                 assignedUserId = currentUserId
    │                 OR id IN (SELECT recordId FROM team_record WHERE teamId IN (mes équipes))
    │               )
    │
    └── Si 'all'  → pas de filtre supplémentaire

Résultat : SELECT retourne uniquement les enregistrements autorisés
```

---

## 6. Flux de la Stream (fil d'activité)

Chaque modification d'un enregistrement "streamable" génère une `Note` :

```text
EntityManager::saveEntity($opportunity)
    │
    ▼
Hook: Stream/HookProcessor::afterSave()
    │
    ├── Compare l'ancienne valeur vs la nouvelle pour chaque champ "audited"
    │   (stage, amount, assignedUser, status...)
    │
    ├── Si champ audité a changé → crée une Note de type "Update"
    │   { entityType: "Opportunity", entityId: "...", type: "Update",
    │     data: { fields: ["stage"], attributes: { before: {...}, after: {...} } } }
    │
    ├── Si assignedUser a changé → crée une Note "Assign" + Notification
    │
    └── La Note est visible dans :
        ├── Le stream de l'Opportunity elle-même
        ├── Le stream de l'Account lié (si configuré)
        └── Le "My Stream" des utilisateurs abonnés à l'enregistrement
```

---

## 7. Cycle de vie d'un Lead jusqu'à la vente

```text
Source externe (formulaire web, email, appel)
    │
    └──→ Lead créé (status: New)
              │
              ▼ assigné à un commercial
         Lead (status: Assigned → In Process)
              │
              ▼ commercial qualifie le prospect
         Lead::convert() — POST /api/v1/Lead/:id/action/convert
              │
              ├──→ Contact créé (nom, email, téléphone copiés)
              ├──→ Account créé (entreprise, site web copiés)
              └──→ Opportunity créée (nom, montant, stage: Prospecting)
                        │
                        ▼ pipeline de vente
                   Opportunity (stage: Qualification)
                        │
                        ▼ Meetings / Calls / Tasks créés et liés
                   Opportunity (stage: Proposal/Price Quote)
                        │
                        ├──→ Document (devis) lié à l'Opportunity
                        │
                        ▼
                   Opportunity (stage: Negotiation/Review)
                        │
                        ▼
                   Opportunity (stage: Closed Won) ← Vente réalisée
```

---

## 8. Import CSV — flux interne

```text
Utilisateur upload CSV
    │
    ▼
POST /api/v1/Import/file (body = contenu brut du CSV)
    │
    ├── Sauvegarde le CSV comme Attachment (data/upload/)
    └── Retourne { "attachmentId": "..." }

Utilisateur configure le mapping et lance
    │
    ▼
POST /api/v1/Import
{ "entityType": "Contact", "attachmentId": "...", "attributeList": [...], "params": {...} }
    │
    ▼
Tools/Import/Service.php::import()
    │
    ├── Lit le CSV ligne par ligne
    ├── Pour chaque ligne :
    │   ├── Mappe les colonnes aux champs EspoCRM
    │   ├── Résout les relations par nom (accountName → accountId)
    │   ├── Crée ou met à jour l'enregistrement via EntityManager
    │   └── En cas d'erreur : crée un ImportError (téléchargeable)
    │
    ├── Enregistre un Import en BDD (historique, stats)
    └── Retourne { "id": "...", "created": N, "updated": N, "failed": N }

Annulation
    │
    ▼
POST /api/v1/Import/:id/revert
    │
    └── Supprime tous les enregistrements créés par cet import
        (via ImportEntity qui lie Import ↔ enregistrements créés)
```

---

## 9. Stockage physique des données

| Type de donnée      | Stockage                                            |
|---------------------|-----------------------------------------------------|
| Enregistrements CRM | MySQL — tables correspondant aux entités            |
| Fichiers uploadés   | `data/upload/` — nommés par UUID                    |
| Cache metadata      | `data/cache/application/` — fichiers PHP sérialisés |
| Cache routes Slim   | `data/cache/application/slim-routes.php`            |
| Logs application    | `data/logs/espo.log`                                |
| Config              | `data/config.php` — généré par l'installeur         |
| Sessions            | MySQL table `auth_token`                            |
| Jobs file           | MySQL table `job`                                   |

---

## 10. Webhooks — données sortantes

EspoCRM peut envoyer des événements vers des systèmes externes.

```text
Enregistrement créé/modifié/supprimé
    │
    ▼
Webhook configuré sur cet événement (Admin → Webhooks)
    │
    ├── Crée un WebhookQueueItem
    └── Job "ProcessWebhookQueue" (toutes les minutes)
            │
            └──→ POST vers l'URL externe
                 { "event": "Contact.create", "data": { ...contact... } }
```

Les webhooks supportent : `.create`, `.update`, `.delete` sur toutes les entités.
