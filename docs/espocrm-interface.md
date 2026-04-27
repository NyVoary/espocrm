# EspoCRM — Interface utilisateur

---

## Navigation principale

L'interface est une SPA (Single Page Application) avec une barre de navigation en haut.

### Barre de navigation

```text
[≡ Menu]  [Logo]  [Comptes] [Contacts] [Leads] [Opportunités] [...] [🔍] [🔔] [👤]
```

- **Menu (≡)** : Accès à tous les modules — affiche une liste avec icônes
- **Barre de modules** : Les 5-8 modules épinglés (configurable par l'admin)
- **Recherche globale (🔍)** : Cherche dans tous les modules simultanément
- **Notifications (🔔)** : Popup des notifications non lues
- **Profil (👤)** : Préférences, mot de passe, déconnexion

---

## Pages types (vues)

### Vue Liste (List View)

URL pattern : `#EntityType`  
Exemple : `#Contact`, `#Opportunity`

```text
[Accounts]                                    [Create Account]
──────────────────────────────────────────────────────────────
[🔍 Search...] [Filtres avancés ▼]            Tri: Name ↑
──────────────────────────────────────────────────────────────
☐  Name               Type       Industry    Assigned User
☐  Rakoto Tech SA     Customer   Technology  Admin
☐  Rasoa Import       Partner    Transport   Admin
──────────────────────────────────────────────────────────────
                                              1–2 sur 2   < >
```

**Actions disponibles :**

- Clic sur un enregistrement → Vue Détail
- Checkbox + "Mass Update" / "Mass Delete" / "Export"
- Bouton "Create" → Formulaire de création
- Colonnes configurables (icône engrenage)

---

### Vue Détail (Detail View)

URL pattern : `#EntityType/view/ID`  
Exemple : `#Contact/view/abc123`

```text
[← Contacts]                    [Edit] [▾ Actions ▾]
═══════════════════════════════════════════════════
Jean Rakoto
Directeur — Rakoto Tech SA

First Name: Jean          Last Name: Rakoto
Email:       jean@rakoto.mg   Phone: +261 34 00 001
Account:     Rakoto Tech SA   Assigned: Admin
───────────────────────────────────────────────────
[Opportunities (2)] [Cases (0)] [Meetings (1)] [Tasks (3)] [Stream]
═══════════════════════════════════════════════════════════════════
Stream:
  ● Admin — Created Jean Rakoto — il y a 2 heures
  ● Admin — Changed title to "Directeur" — il y a 1 heure
```

**Panneaux latéraux (Relations) :**
Chaque entité affiche ses entités liées dans des sous-panneaux en bas de page.
Cliquer sur "+" dans un sous-panneau crée un nouvel enregistrement lié.

---

### Formulaire de création/modification (Edit View)

URL pattern : `#EntityType/create` ou `#EntityType/edit/ID`

Formulaire en sections avec validation inline. Les champs requis sont marqués d'un `*`.

---

### Vue Kanban (Pipeline)

Disponible pour Opportunity (et les entités avec un champ `stage`/`status`).

```text
Prospecting    Qualification   Proposal    Closed Won
─────────────  ─────────────  ──────────  ──────────
[Deal A 50k]   [Deal B 18k]   [Deal C     [Deal D
                               25k]        10k] ✓
[Deal E 30k]
```

Drag & drop entre colonnes pour changer le stage.

---

## Modules et leurs URL

| Module         | URL                     | Description                 |
|----------------|-------------------------|-----------------------------|
| Accounts       | `#Account`              | Liste des comptes           |
| Contacts       | `#Contact`              | Liste des contacts          |
| Leads          | `#Lead`                 | Leads entrants              |
| Opportunities  | `#Opportunity`          | Pipeline de vente           |
| Cases          | `#Case`                 | Tickets support             |
| Meetings       | `#Meeting`              | Réunions                    |
| Calls          | `#Call`                 | Appels                      |
| Tasks          | `#Task`                 | Tâches                      |
| Documents      | `#Document`             | Documents                   |
| Campaigns      | `#Campaign`             | Campagnes marketing         |
| Knowledge Base | `#KnowledgeBaseArticle` | Base de connaissances       |
| Emails         | `#Email`                | Boîte email                 |
| Calendar       | `#Calendar`             | Calendrier                  |
| Reports        | `#Report`               | Rapports (module optionnel) |
| Import         | `#Import`               | Historique des imports      |

---

## Tableau de bord (Dashboard)

Page d'accueil après connexion. Composée de **Dashlets** configurables :

| Dashlet                | Description                                                  |
|------------------------|--------------------------------------------------------------|
| My Activities          | Activités (Meetings, Calls, Tasks) assignées à l'utilisateur |
| My Opportunities       | Pipeline personnel                                           |
| Opportunity Statistics | Chiffre par stage (graphique)                                |
| My Leads               | Leads assignés                                               |
| Recent Activities      | Dernières activités créées ou modifiées                      |
| Stream                 | Fil d'activité global de l'utilisateur                       |
| My Tasks               | Tâches de l'utilisateur                                      |
| Saved Reports          | Graphiques de rapports sauvegardés                           |

Chaque utilisateur peut personnaliser son tableau de bord (ajouter/supprimer/déplacer des dashlets).

---

## Calendrier

Page `#Calendar` — affiche :

- Meetings (avec durée)
- Calls (avec durée)
- Tasks (avec date d'échéance)

**Vues disponibles :** Jour / Semaine / Mois / Timeline

La Timeline permet de voir les disponibilités de plusieurs utilisateurs en parallèle
(utile pour planifier des réunions).

---

## Zone Admin

Accessible via `#Admin` (admin only).

### Sections principales

**Utilisateurs et sécurité :**

- Users (créer, gérer les utilisateurs)
- Roles (permissions ACL)
- Teams (équipes)
- Portals (portails clients)
- Authentication (2FA, LDAP, OAuth)

**Personnalisation :**

- Entity Manager : créer des entités custom, ajouter des champs
- Layout Manager : modifier les layouts liste/détail/formulaire
- Field Manager : configuration avancée des champs
- Label Manager : changer les libellés
- Dynamic Logic : champs conditionnels (afficher/cacher selon valeur)
- Formula : champs calculés automatiquement

**Intégrations :**

- Integrations (Google Calendar, Exchange...)
- Inbound Emails (boîtes IMAP partagées)
- Outbound Emails (SMTP sortant)
- Webhooks
- API Users (clés API)

**Outils système :**

- Scheduled Jobs : configuration des crons
- Import : historique des imports
- Currency : devises et taux de conversion
- Working Time Calendars : calendriers de travail
- Rebuild : vider le cache et régénérer les assets
- Clear Cache

---

## Recherche et filtres

### Recherche globale

Barre de recherche en haut à droite. Cherche simultanément dans :
Contact, Account, Lead, Opportunity, Email, Case, etc.

API : `GET /api/v1/GlobalSearch?q=texte`

### Recherche par module

Chaque vue liste a une barre de recherche et des **filtres avancés** :

1. Cliquer sur le filtre avancé (▼)
2. Sélectionner les champs à filtrer
3. Choisir l'opérateur (contains, equals, between, is null...)
4. Entrer la valeur
5. Possibilité de sauvegarder le filtre comme "Saved Search"

---

## Préférences utilisateur

`#Preferences` ou via le menu profil.

| Préférence          | Description                                          |
|---------------------|------------------------------------------------------|
| Language            | Langue de l'interface                                |
| Date Format         | Format d'affichage des dates                         |
| Time Format         | 12h ou 24h                                           |
| Time Zone           | Fuseau horaire de l'utilisateur                      |
| Currency            | Devise par défaut                                    |
| Default Dashboard   | Tableau de bord partagé à utiliser                   |
| Auto-follow         | S'abonner automatiquement aux records créés/modifiés |
| Email Notifications | Quand recevoir des emails de notification            |
| Signature           | Signature email personnelle                          |

---

## Import via interface — pas à pas

1. Menu **≡** → **Import**
2. Bouton **"Create Import"** (haut droite)
3. **Écran 1 — Upload :**
   - Entity Type : sélectionner le type (Account, Contact...)
   - File : glisser le fichier CSV
   - Header Row : Yes
   - Delimiter : `,` (virgule)
4. **Écran 2 — Mapping :** associer colonnes CSV → champs EspoCRM
5. **Écran 3 — Options :** Skip Duplicates, Action (Create Only...)
6. **Run Import**
7. Suivre la progression, télécharger les erreurs si besoin
8. Pour annuler : revenir dans l'historique → bouton **Revert**

---

## Portail client

URL séparée (ex: `http://localhost/portal/`).

Le portail permet aux clients externes (pas employés) de :

- Soumettre et suivre leurs Cases (tickets support)
- Consulter la base de connaissances
- Voir leurs informations

Chaque portail a ses propres rôles (`PortalRole`) avec des permissions limitées.
