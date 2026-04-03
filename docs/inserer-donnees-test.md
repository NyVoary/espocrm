# Insérer des données de test dans EspoCRM

Deux approches : script seed (ORM interne PHP) et import CSV via l'interface.

> **Formats supportés :** EspoCRM n'accepte que le **CSV** pour l'import — confirmé dans le code source (`Import.php` ligne 128, `Service.php` ligne 342). Excel (.xlsx), JSON et TXT ne sont pas supportés nativement. Si tes données sont dans Excel, exporte-les en CSV avant d'importer.
> **Script seed :** Ce n'est pas un appel API HTTP — c'est un script PHP qui utilise l'ORM interne directement, exactement comme `cron.php` ou `command.php`. EspoCRM est conçu pour ce type d'utilisation CLI.

---

## Approche 1 — Script seed (API interne PHP)

### Principe

Le script `docker/seed.php` utilise l'ORM interne d'EspoCRM directement,
comme le font `cron.php` ou `command.php`. Il ne passe pas par HTTP —
il parle directement à la base de données via les services PHP.

**Avantages :**
- Rapide (pas d'overhead HTTP)
- Crée les relations entre entités (contact lié à son account, etc.)
- Respecte la logique métier (hooks, validations)
- Reproductible en une commande

### Utilisation

```powershell
# Insérer les données
docker-compose exec app php /var/www/html/docker/seed.php

# Remettre à zéro puis réinsérer
docker-compose exec app php /var/www/html/docker/seed.php --reset
```

### Ce que le script insère

| Entité | Quantité | Détails |
|---|---|---|
| Accounts | 8 | Technology, Finance, Construction, Agriculture, Santé... |
| Contacts | 10 | Liés aux comptes |
| Leads | 6 | Statuts variés (New, In Process, Recycled...) |
| Opportunités | 10 | Tous les stages (Prospecting → Closed Won/Lost) |
| Réunions | 5 | Planned, Held, Not Held |
| Appels | 4 | Inbound et Outbound |
| Tâches | 8 | Priorités et statuts variés |

### Comment le modifier

Le fichier est à [docker/seed.php](../docker/seed.php).
Pour ajouter des données, il suffit d'ajouter des entrées dans les tableaux :

```php
$accounts = [
    // ajoute une ligne ici
    ['name' => 'Nouvelle Entreprise', 'industry' => 'Technology', 'type' => 'Customer'],
];
```

Pour ajouter un nouveau type d'entité, suis ce pattern :

```php
// Le nom correspond exactement au nom du Controller dans Controllers/
create($em, 'NomEntite', [
    'champ1' => 'valeur1',
    'champ2' => 'valeur2',
]);
```

> **Comment trouver les noms de champs ?** Va dans EspoCRM → Admin →
> Entity Manager → sélectionne l'entité → Fields. Ou utilise l'API :
> `GET /api/v1/Metadata` et cherche `entityDefs.NomEntite.fields`.

---

## Approche 2 — Import CSV via l'interface

### Principe

EspoCRM a un importeur CSV intégré accessible depuis l'interface.
Tu prépares un fichier CSV avec les données, tu le glisses dans l'interface,
tu mappes les colonnes aux champs EspoCRM, et c'est importé.

### Étape 1 — Préparer le fichier CSV

Règles à respecter :
- Encodage **UTF-8**
- Séparateur : **virgule** `,` (par défaut) ou point-virgule `;`
- Première ligne = **noms des colonnes** (headers)
- Dates au format `YYYY-MM-DD` ou `DD/MM/YYYY` (configurable à l'import)

**Exemple pour des Contacts (`contacts.csv`) :**

```csv
firstName,lastName,emailAddress,phoneNumber,title,accountName
Jean,Rakoto,jean@example.mg,+261 34 00 00001,Directeur,Rakoto Tech SA
Soa,Rasoa,soa@example.mg,+261 34 00 00002,Responsable,Rasoa Import
Mialy,Andriantsoa,mialy@example.mg,+261 34 00 00003,Comptable,Mialy Consulting
```

**Exemple pour des Comptes (`accounts.csv`) :**

```csv
name,industry,type,website,emailAddress,phoneNumber
Rakoto Tech SA,Technology,Customer,https://rakoto.mg,contact@rakoto.mg,+261 20 22 00001
Rasoa Import,Transportation,Partner,https://rasoa.mg,info@rasoa.mg,+261 20 22 00002
```

**Exemple pour des Opportunités (`opportunities.csv`) :**

```csv
name,stage,amount,closeDate,probability,accountName
Projet ERP Rakoto,Prospecting,25000,2026-07-01,20,Rakoto Tech SA
Refonte SI Rasoa,Qualification,18000,2026-06-15,40,Rasoa Import
```

> **Conseil** : importe toujours les **Accounts en premier**, puis les Contacts
> et Opportunités — EspoCRM peut résoudre les relations par nom de compte (`accountName`).

### Étape 2 — Lancer l'import

1. Dans EspoCRM, va dans le menu **hamburger (≡)** en haut à gauche
2. Cherche **Import** dans la liste (ou via la barre de recherche)
3. Clique sur **"Create Import"** (bouton en haut à droite)

### Étape 3 — Configurer l'import

**Écran 1 — Upload et paramètres généraux :**

| Paramètre | Valeur recommandée |
|---|---|
| Entity Type | Sélectionne le type : Contact, Account, etc. |
| File | Glisse ton fichier CSV |
| Header Row | **Yes** (si ton CSV a une ligne de headers) |
| Person Name Format | First Last |
| Delimiter | `,` (virgule) |
| Date Format | `YYYY-MM-DD` ou selon ton CSV |
| Decimal Mark | `.` (point) |

Clique **Next**.

**Écran 2 — Mapping des colonnes :**

EspoCRM affiche deux colonnes :
- **Gauche** : nom de la colonne dans ton CSV
- **Droite** : champ EspoCRM correspondant (à sélectionner)

Exemple de mapping pour Contact :

| Colonne CSV | Champ EspoCRM |
|---|---|
| `firstName` | First Name |
| `lastName` | Last Name |
| `emailAddress` | Email Address |
| `phoneNumber` | Phone Number |
| `title` | Title |
| `accountName` | Account Name |

Clique **Next**.

**Écran 3 — Options avancées :**

| Option | Description |
|---|---|
| Skip Duplicates | Ignore les lignes si l'enregistrement existe déjà |
| Update Duplicates | Met à jour les enregistrements existants |
| Action | `Create Only`, `Create and Update`, ou `Update Only` |
| Silent Mode | N'envoie pas de notifications pendant l'import |

Pour un premier import de test : laisse les valeurs par défaut.

Clique **Run Import**.

### Étape 4 — Suivre la progression

EspoCRM affiche une barre de progression. Une fois terminé, il affiche :
- Nombre d'enregistrements **créés**
- Nombre d'enregistrements **mis à jour**
- Nombre d'**erreurs** (clique dessus pour voir les lignes problématiques)

### Étape 5 — En cas d'erreur

Si des lignes ont échoué :
1. Clique sur le nombre d'erreurs → télécharge le fichier d'erreurs
2. Le fichier contient les lignes problématiques + un message d'erreur
3. Corrige le CSV et relance l'import uniquement sur ces lignes

### Annuler un import (Revert)

Si l'import a créé de mauvaises données, tu peux l'annuler :
1. Va dans **Import** → liste des imports passés
2. Clique sur l'import concerné
3. Bouton **"Revert"** → supprime tous les enregistrements créés par cet import

---

## Comparaison des deux approches

| Critère | Script seed | Import CSV |
|---|---|---|
| Vitesse d'exécution | Rapide | Moyen (traitement ligne par ligne) |
| Volume de données | Illimité | Illimité |
| Relations entre entités | Automatiques (IDs directs) | Par nom (peut échouer si nom inexistant) |
| Reproductible | Oui (`--reset` puis relance) | Oui (relance le même CSV) |
| Correction d'erreurs | Modifier le script PHP | Modifier le CSV |
| Sans accès au code | Non | Oui |
| Données depuis Excel | Non (doit coder) | Oui (export Excel → CSV) |
| Annulation | `--reset` supprime tout | Bouton "Revert" dans l'interface |

**Règle pratique :**
- Tu génères les données toi-même en PHP → **script seed**
- Tu as un fichier Excel/CSV existant à importer → **import CSV**

---

## Trouver les noms de champs exacts

Avant de préparer un CSV ou de modifier le seed, il faut connaître les noms
exacts des champs EspoCRM.

**Méthode 1 — Interface admin :**
Admin → Entity Manager → sélectionne l'entité → Fields
→ colonne "Name" = nom technique du champ à utiliser dans le CSV / le seed.

**Méthode 2 — API Metadata :**
```powershell
$headers = @{ Authorization = "Basic " + [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("admin:MOTDEPASSE")) }
Invoke-WebRequest -Uri "http://localhost/api/v1/Metadata" -Headers $headers -UseBasicParsing |
    Select-Object -ExpandProperty Content | ConvertFrom-Json |
    Select-Object -ExpandProperty entityDefs |
    Select-Object -ExpandProperty Contact |
    Select-Object -ExpandProperty fields
```

**Méthode 3 — Lire un enregistrement existant via l'API :**
Crée un enregistrement à la main dans l'interface, puis :
```powershell
Invoke-WebRequest -Uri "http://localhost/api/v1/Contact/ID_ICI" -Headers $headers -UseBasicParsing
```
La réponse JSON te montre tous les champs et leur nom exact.
