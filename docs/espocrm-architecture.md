# EspoCRM — Architecture technique

---

## Stack technique

| Couche          | Technologie                                          |
|-----------------|------------------------------------------------------|
| Serveur web     | nginx (reverse proxy + fichiers statiques)           |
| Application     | PHP 8.3-FPM (FastCGI)                                |
| Framework HTTP  | Slim 4 (routing PSR-7)                               |
| ORM             | ORM maison EspoCRM (pas Doctrine)                    |
| Base de données | MySQL 8.0                                            |
| Frontend        | SPA Backbone.js (client JS)                          |
| Build frontend  | Node.js + Grunt                                      |
| DI Container    | Container maison (injection par classe ou interface) |

---

## Structure des répertoires

```text
espocrm/
├── public/                  # Racine nginx — seul dossier exposé
│   ├── index.php            # Point d'entrée SPA (sert le HTML de l'app JS)
│   ├── api/v1/index.php     # Point d'entrée API REST
│   ├── api/v1/portal-access/index.php  # API du portail client
│   └── client/              # Fichiers JS/CSS compilés (servis en statique)
│
├── application/
│   └── Espo/
│       ├── Core/            # Framework interne (DI, ORM, routing, ACL...)
│       ├── Controllers/     # Controllers système (Email, User, Import...)
│       ├── Entities/        # Entités système (User, Email, Attachment...)
│       ├── Services/        # Services système (Record, Stream, User...)
│       ├── Tools/           # Logique métier découplée des controllers
│       ├── Resources/
│       │   ├── routes.json  # Routes API explicites
│       │   └── metadata/    # Définitions entités, vues, labels
│       └── Modules/
│           └── Crm/         # Module CRM (Contacts, Accounts, Leads...)
│               ├── Controllers/
│               ├── Entities/
│               ├── Tools/
│               └── Resources/
│                   ├── routes.json
│                   └── metadata/
│                       ├── entityDefs/  # Champs, relations, index
│                       ├── clientDefs/ # Vues, layouts, couleurs
│                       └── scopes/     # Permissions par entité
│
├── data/                   # Volume Docker — données runtime
│   ├── config.php          # Config générée par l'installeur
│   ├── logs/               # Logs application
│   ├── cache/              # Cache metadata, routes Slim, i18n
│   └── upload/             # Fichiers uploadés (Attachment)
│
├── vendor/                 # Dépendances Composer
├── custom/                 # Personnalisations sans modifier le core
│   └── Espo/Custom/        # Même structure que application/Espo/
└── docker/                 # Infrastructure Docker (hors EspoCRM officiel)
```

---

## Cycle de vie d'une requête API

```text
Client HTTP
    │
    ▼
nginx (port 80)
    │  fastcgi_pass app:9000
    │  SCRIPT_NAME = /api/v1/index.php  ← critique pour le routing Slim
    ▼
PHP-FPM (port 9000)
    │
    ▼
public/api/v1/index.php
    │  new Application()
    │  $app->run()
    ▼
Core/Application.php
    │  buildContainer()  → DI container
    │  buildSlimApp()    → Slim 4
    ▼
Core/Api/Route.php::unify()
    │  Fusionne les routes :
    │  1. custom/Espo/Custom/Resources/routes.json
    │  2. application/Espo/Modules/*/Resources/routes.json
    │  3. application/Espo/Resources/routes.json
    │
    │  detectBasePath() lit $_SERVER['SCRIPT_NAME']
    │  → extrait "/api/v1" comme basePath Slim
    ▼
Slim Router
    │  Correspond la route (ex: GET /api/v1/Contact/{id})
    │
    ├─ Route explicite → actionClassName → instancié via DI
    │
    └─ Route générique /:controller/:id
           │
           ▼
       Core/Api/ActionHandler.php
           │
           ▼
       Controllers/RecordBase.php (ou controller spécifique)
           │
           ├── readAction()   → GET /:controller/:id
           ├── listAction()   → GET /:controller
           ├── createAction() → POST /:controller
           ├── updateAction() → PUT /:controller/:id
           └── deleteAction() → DELETE /:controller/:id
                   │
                   ▼
           Tools/ ou Services/Record.php
                   │
                   ▼
           ORM EntityManager
                   │
                   ▼
           MySQL
```

---

## Couche DI — Injection de dépendances

EspoCRM utilise un container maison (`Core/Container.php`).
Les dépendances sont injectées par **constructeur** en typant les paramètres.

```php
// Exemple : action qui reçoit ses dépendances automatiquement
class GetList implements Action
{
    public function __construct(
        private RecordService $service,
        private Acl $acl,
    ) {}
}
```

Le container résout les types via réflexion PHP. Il n'y a pas de fichier de configuration
des bindings — les interfaces sont bindées aux implémentations dans `Core/Binding/`.

---

## Couche ORM

L'ORM est entièrement maison (pas Doctrine, pas Eloquent).

**Composants clés :**

| Classe          | Rôle                                                                   |
|-----------------|------------------------------------------------------------------------|
| `EntityManager` | Point d'entrée : `createEntity()`, `getEntityById()`, `removeEntity()` |
| `Entity`        | Classe de base de toutes les entités                                   |
| `Repository`    | Requêtes : `find()`, `findOne()`, `count()` + QueryBuilder             |
| `QueryBuilder`  | Construction de requêtes SQL typées                                    |
| `Mapper`        | Traduit Entity ↔ rows SQL                                              |
| `Metadata`      | Définitions des champs et relations (lu depuis les JSON)               |

**Utilisation dans un script seed :**

```php
$em = $container->getByClass(EntityManager::class);

// Créer
$entity = $em->createEntity('Contact', [
    'firstName' => 'Jean',
    'lastName'  => 'Rakoto',
    'accountId' => $accountId,
]);

// Lire
$contact = $em->getEntityById('Contact', $id);

// Mettre à jour
$contact->set('status', 'Active');
$em->saveEntity($contact);

// Supprimer
$em->removeEntity($contact);

// Requête
$list = $em->getRDBRepository('Contact')
    ->where(['accountId' => $accountId])
    ->order('lastName')
    ->find();
```

---

## Couche ACL

EspoCRM a un système ACL à deux niveaux :

**1. Scope-level** (par entité) :

- `no` : pas d'accès
- `own` : uniquement ses propres enregistrements (`assignedUserId = currentUser`)
- `team` : enregistrements des équipes dont on fait partie
- `all` : tous les enregistrements

**2. Field-level** (par champ) :

- `read-only` / `edit` / `no` pour chaque champ individuellement

Les permissions sont définies dans `Role` et `PortalRole`, puis assignées aux Users et Teams.

```php
// Dans un controller/action
if (!$this->acl->checkScope('Contact', 'read')) {
    throw new Forbidden();
}

if (!$this->acl->checkEntity($contact, 'edit')) {
    throw new Forbidden();
}
```

---

## Couche Service

Les Services encapsulent la logique métier. `Services/Record.php` est la classe de base
pour tous les CRUD. Elle gère :

- Validation des champs requis
- Application des hooks `before/afterCreate`, `before/afterUpdate`...
- Vérification ACL
- Déclenchement des événements Stream
- Envoi de notifications

Les modules peuvent **étendre** ce service :

```text
Modules/Crm/Tools/Lead/ConvertService.php  → logique de conversion Lead → Contact
Modules/Crm/Tools/Opportunity/Service.php  → calcul probabilité/montant pondéré
```

---

## Hooks système

Les hooks permettent d'exécuter du code avant/après chaque opération ORM.
Ils sont définis dans `Hooks/` (core ou modules) :

```php
// Hooks/Contact/BeforeSave.php
class BeforeSave
{
    public function process(Entity $entity, array $options): void
    {
        // Exécuté avant chaque sauvegarde d'un Contact
    }
}
```

**Hooks disponibles :**

- `beforeSave` / `afterSave`
- `beforeRemove` / `afterRemove`
- `afterRelate` / `afterUnrelate`

---

## Jobs planifiés (Cron)

`cron.php` est exécuté toutes les minutes (container `cron` dans docker-compose).

Les jobs sont définis dans `Resources/metadata/app/scheduledJobs.json` :

| Job                           | Fréquence        | Rôle                               |
|-------------------------------|------------------|------------------------------------|
| `ProcessInboundEmails`        | toutes les 3 min | Récupère les emails IMAP           |
| `SendEmailNotifications`      | toutes les 2 min | Envoie les notifications email     |
| `SendEmailReminders`          | toutes les 5 min | Envoie les rappels d'activité      |
| `CleanupDeletedRecords`       | 1x/jour          | Purge les soft-deletes             |
| `CleanupEntityDuplicateCheck` | 1x/jour          | Purge les caches de déduplications |
| `AuthTokenControl`            | 1x/heure         | Expire les tokens d'auth inactifs  |
| `MassEmailSending`            | toutes les 5 min | Envoi des campagnes email          |

---

## Frontend — Architecture JS

Le frontend est une **SPA Backbone.js** (pas React, pas Vue).

```text
public/client/
├── lib/           # Librairies tierces (Backbone, jQuery, Handlebars...)
├── modules/crm/   # Vues et layouts du module CRM compilés
└── src/           # Sources non compilées (development uniquement)
```

La SPA charge au démarrage :

1. `GET /api/v1/App/user` → profil utilisateur + permissions
2. `GET /api/v1/Metadata` → définitions entités/vues
3. `GET /api/v1/I18n` → traductions
4. `GET /api/v1/Settings` → config applicative

Ensuite elle gère la navigation côté client (hash routing) et appelle l'API REST
pour chaque opération CRUD.

---

## Metadata — Système de définition des entités

Tout comportement d'une entité est défini dans des fichiers JSON sous `Resources/metadata/` :

| Dossier       | Contenu                                                             |
|---------------|---------------------------------------------------------------------|
| `entityDefs/` | Champs (type, requis, readOnly...) + relations                      |
| `clientDefs/` | Vues frontend (liste, détail, formulaire), couleurs des stages      |
| `scopes/`     | Activation ACL, streamable, importable, exportable...               |
| `app/`        | Config globale (scheduledJobs, dashlets, etc.)                      |
| `fields/`     | Définition des types de champs (varchar, currency, linkMultiple...) |

La metadata est **fusionnée** dans cet ordre (le dernier gagne) :
`core` → `modules` → `custom/`

Cela permet de customiser sans toucher au code source.
