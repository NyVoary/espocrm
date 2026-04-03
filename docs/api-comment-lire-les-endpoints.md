# Comment lire les endpoints de l'API EspoCRM

Ce document explique comment retrouver et comprendre les endpoints API directement dans le code source.
Tout ce qui est dans la collection Postman a été déduit de cette lecture.

---

## Architecture en deux couches

EspoCRM expose ses endpoints via deux mécanismes distincts :

```txt
1. Routes explicites   → fichiers routes.json         (endpoints spécifiques)
2. Routes génériques   → routes.json + Controllers/   (CRUD automatique sur toutes les entités)
```

---

## Couche 1 — Les fichiers routes.json

### Où sont-ils ?

Il y en a deux dans le projet :

```txt
application/Espo/Resources/routes.json              ← routes du core (Auth, Settings, Import, Export...)
application/Espo/Modules/Crm/Resources/routes.json  ← routes du module CRM (Activities, Calendar...)
```

Si tu ajoutes un module custom, il aurait son propre :

```txt
custom/Espo/Modules/MonModule/Resources/routes.json
```

### Comment ils sont chargés ?

Le fichier [application/Espo/Core/Utils/Route.php](../application/Espo/Core/Utils/Route.php) (méthode `unify()`, ligne 115) les fusionne dans cet ordre de priorité :

```txt
1. custom/Espo/Custom/Resources/routes.json        (priorité max, écrase tout)
2. application/Espo/Modules/*/Resources/routes.json (modules, ordre inversé)
3. application/Espo/Resources/routes.json           (core, priorité min)
```

Si deux fichiers définissent la même route (même method + même path), la première trouvée gagne.

### Structure d'une entrée routes.json

```json
{
    "route": "/Settings",
    "method": "get",
    "noAuth": true,
    "params": {
        "controller": "Settings"
    }
}
```

ou avec une action class directe (style plus moderne) :

```json
{
    "route": "/App/user",
    "method": "get",
    "actionClassName": "Espo\\Tools\\App\\Api\\GetUser"
}
```

| Champ               | Rôle                                               |
|---------------------|----------------------------------------------------|
| `route`             | Le chemin URL (`:id` = paramètre dynamique)        |
| `method`            | `get`, `post`, `put`, `patch`, `delete`, `options` |
| `noAuth`            | `true` = pas d'authentification requise            |
| `params.controller` | Nom du controller PHP à appeler                    |
| `params.action`     | Méthode du controller à appeler                    |
| `actionClassName`   | Classe PHP directe (bypasse le controller)         |

### Comment lire l'URL finale ?

Toutes les routes sont préfixées par `/api/v1/` par nginx (notre config Docker).
Le code PHP lui-même ne connaît pas ce préfixe — c'est `detectBasePath()` dans
[Route.php:207](../application/Espo/Core/Utils/Route.php) qui le détecte dynamiquement
depuis `$_SERVER['SCRIPT_NAME']`.

Donc `"route": "/Settings"` dans le JSON → `GET http://localhost/api/v1/Settings` en HTTP.

---

## Couche 2 — Les routes génériques + Controllers

### Les routes génériques (le pattern universel)

À la fin de [application/Espo/Resources/routes.json](../application/Espo/Resources/routes.json),
il y a ces routes génériques :

```json
{ "route": "/:controller",       "method": "get",    "params": { "action": "index"  } }
{ "route": "/:controller",       "method": "post",   "params": { "action": "create" } }
{ "route": "/:controller/:id",   "method": "get",    "params": { "action": "read"   } }
{ "route": "/:controller/:id",   "method": "put",    "params": { "action": "update" } }
{ "route": "/:controller/:id",   "method": "patch",  "params": { "action": "update" } }
{ "route": "/:controller/:id",   "method": "delete", "params": { "action": "delete" } }
{ "route": "/:controller/:id/:link", "method": "get", "params": { "action": "listLinked" } }
```

`:controller` est remplacé par n'importe quel nom de controller existant.

### Les controllers

Chaque fichier dans [application/Espo/Controllers/](../application/Espo/Controllers/)
et [application/Espo/Modules/Crm/Controllers/](../application/Espo/Modules/Crm/Controllers/)
correspond à un `:controller` dans l'URL.

**Exemple :**

```txt
Controllers/Account.php → /api/v1/Account
Controllers/Contact.php → /api/v1/Contact
Controllers/User.php    → /api/v1/User
```

La plupart des controllers sont vides :

```php
// application/Espo/Modules/Crm/Controllers/Account.php
class Account extends \Espo\Core\Controllers\Record {}
```

Ils héritent tout de `Record`, qui lui-même hérite de `RecordBase`.

### Ce que RecordBase fournit automatiquement

[application/Espo/Core/Controllers/RecordBase.php](../application/Espo/Core/Controllers/RecordBase.php)
implémente toutes les actions CRUD :

| Méthode PHP dans RecordBase | Route générique activée     | HTTP                           |
|-----------------------------|-----------------------------|--------------------------------|
| `getActionIndex()`          | `GET /:controller`          | Liste tous les enregistrements |
| `postActionCreate()`        | `POST /:controller`         | Crée un enregistrement         |
| `getActionRead()`           | `GET /:controller/:id`      | Lit un enregistrement          |
| `putActionUpdate()`         | `PUT /:controller/:id`      | Met à jour                     |
| `deleteActionDelete()`      | `DELETE /:controller/:id`   | Supprime                       |
| `getActionListLinked()`     | `GET /:controller/:id/:link`| Liste les relations            |

**Convention de nommage :** `{httpMethod}Action{NomAction}` → Slim appelle la bonne méthode.

---

## Comment retrouver un endpoint inconnu — méthode pratique

**Étape 1** — Cherche dans les routes.json explicites :

```bash
# Dans VS Code ou grep
grep -r "Settings" application/Espo/Resources/routes.json
grep -r "Activities" application/Espo/Modules/Crm/Resources/routes.json
```

**Étape 2** — Si pas trouvé, cherche le controller :

```bash
# Existe-t-il un fichier Contact.php dans Controllers/ ?
ls application/Espo/Controllers/
ls application/Espo/Modules/Crm/Controllers/
```

Si oui → les routes CRUD sont automatiquement disponibles via les routes génériques.

**Étape 3** — Cherche les actions supplémentaires dans le controller :

```bash
# Les méthodes "postActionXxx" ou "getActionXxx" dans le controller
grep -r "Action" application/Espo/Modules/Crm/Controllers/Lead.php
```

Une méthode `postActionConvert()` dans `Lead.php` donne l'endpoint :
`POST /api/v1/Lead/:id/action/convert`

**Étape 4** — Utilise l'endpoint OpenAPI généré dynamiquement :

```txt
GET http://localhost/api/v1/OpenApi
```

EspoCRM génère une spec OpenAPI complète à la volée depuis ses métadonnées.
C'est la source de vérité la plus complète — tu peux l'importer directement dans Postman.

---

## Résumé visuel

```txt
routes.json (explicites)              Controllers/ (génériques)
         │                                     │
         │  /Settings → Settings controller    │  Account.php → hérite de Record
         │  /App/user → GetUser class          │  → GET    /api/v1/Account
         │  /Kanban/:entityType → GetData      │  → POST   /api/v1/Account
         │  /Activities → GetCalendar          │  → GET    /api/v1/Account/:id
         │                                     │  → PUT    /api/v1/Account/:id
         │                                     │  → DELETE /api/v1/Account/:id
         │                                     │  → GET    /api/v1/Account/:id/contacts
         │                                     │
         └──────────────┬──────────────────────┘
                        │
                 Route.php::unify()
                        │
                  Slim 4 router
                        │
              public/api/v1/index.php
```

---

## Fichiers clés à retenir

| Fichier                                                                                                     | Rôle                                      |
|-------------------------------------------------------------------------------------------------------------|-------------------------------------------|
| [application/Espo/Resources/routes.json](../application/Espo/Resources/routes.json)                         | Routes explicites du core                 |
| [application/Espo/Modules/Crm/Resources/routes.json](../application/Espo/Modules/Crm/Resources/routes.json) | Routes explicites du module CRM           |
| [application/Espo/Core/Utils/Route.php](../application/Espo/Core/Utils/Route.php)                           | Fusionne tous les routes.json             |
| [application/Espo/Core/Controllers/RecordBase.php](../application/Espo/Core/Controllers/RecordBase.php)     | Implémente le CRUD générique              |
| [application/Espo/Core/Controllers/Record.php](../application/Espo/Core/Controllers/Record.php)             | Ajoute listLinked, createLink, removeLink |
| [application/Espo/Modules/Crm/Controllers/](../application/Espo/Modules/Crm/Controllers/)                   | Un fichier par entité CRM                 |
| [application/Espo/Core/Api/Starter.php](../application/Espo/Core/Api/Starter.php)                           | Passe les routes à Slim 4                 |
