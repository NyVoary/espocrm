# IMPORT / EXPORT FICHIERS — REACT

---

## PARTIE A — EXPORT (générer un fichier depuis les données)

### A1. Export CSV (sans librairie)

```jsx
// src/utils/exportUtils.js

export function exportCSV(data, nomFichier = 'export.csv') {
  if (!data || data.length === 0) return

  // Entêtes = clés du premier objet
  const entetes = Object.keys(data[0])
  
  // Lignes
  const lignes = data.map(row =>
    entetes.map(k => `"${row[k] ?? ''}"`).join(',')
  )

  const contenu = [entetes.join(','), ...lignes].join('\n')
  const blob = new Blob([contenu], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)

  const lien = document.createElement('a')
  lien.href = url
  lien.download = nomFichier
  lien.click()
  URL.revokeObjectURL(url)
}
```

**Utilisation dans un composant :**

```jsx
import { exportCSV } from '../utils/exportUtils'

// Dans le composant :
const handleExportCSV = () => {
  exportCSV(produits, 'produits.csv')
}

// Dans le JSX :
<button onClick={handleExportCSV}>Exporter CSV</button>
```

---

### A2. Export JSON

```js
// src/utils/exportUtils.js  (ajouter à la suite)

export function exportJSON(data, nomFichier = 'export.json') {
  const contenu = JSON.stringify(data, null, 2)
  const blob = new Blob([contenu], { type: 'application/json' })
  const url = URL.createObjectURL(blob)

  const lien = document.createElement('a')
  lien.href = url
  lien.download = nomFichier
  lien.click()
  URL.revokeObjectURL(url)
}
```

```jsx
import { exportJSON } from '../utils/exportUtils'

<button onClick={() => exportJSON(produits, 'produits.json')}>
  Exporter JSON
</button>
```

---

### A3. Export Excel (avec librairie xlsx)

```bash
npm install xlsx
```

```js
// src/utils/exportUtils.js  (ajouter)
import * as XLSX from 'xlsx'

export function exportExcel(data, nomFichier = 'export.xlsx') {
  const worksheet = XLSX.utils.json_to_sheet(data)
  const workbook = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Données')
  XLSX.writeFile(workbook, nomFichier)
}
```

```jsx
import { exportExcel } from '../utils/exportUtils'

<button onClick={() => exportExcel(produits, 'produits.xlsx')}>
  Exporter Excel
</button>
```

---

## PARTIE B — IMPORT (lire un fichier + envoyer via API de création)

> **Principe :** on lit le fichier côté frontend (CSV ou Excel),
> on parcourt les lignes, et on appelle `produitService.create()` pour chaque ligne.

### B1. Import CSV → API création

```jsx
// src/components/ImportCSV.jsx

import { useState } from 'react'
import produitService from '../services/produitService'

function ImportCSV() {
  const [message, setMessage] = useState('')
  const [loading, setLoading] = useState(false)

  const handleFichier = async (e) => {
    const fichier = e.target.files[0]
    if (!fichier) return

    const texte = await fichier.text()
    const lignes = texte.trim().split('\n')
    const entetes = lignes[0].split(',').map(h => h.trim().replace(/"/g, ''))

    const donnees = lignes.slice(1).map(ligne => {
      const valeurs = ligne.split(',').map(v => v.trim().replace(/"/g, ''))
      const obj = {}
      entetes.forEach((key, i) => obj[key] = valeurs[i])
      return obj
    })

    setLoading(true)
    let succes = 0
    let erreurs = 0

    for (const item of donnees) {
      try {
        await produitService.create(item)
        succes++
      } catch (err) {
        erreurs++
        console.error('Erreur pour :', item, err.response?.data)
      }
    }

    setLoading(false)
    setMessage(`Import terminé : ${succes} créés, ${erreurs} erreurs`)
  }

  return (
    <div>
      <h3>Importer CSV</h3>
      <input type="file" accept=".csv" onChange={handleFichier} />
      {loading && <p>Import en cours...</p>}
      {message && <p>{message}</p>}
    </div>
  )
}

export default ImportCSV
```

---

### B2. Import Excel → API création

```bash
npm install xlsx
```

```jsx
// src/components/ImportExcel.jsx

import { useState } from 'react'
import * as XLSX from 'xlsx'
import produitService from '../services/produitService'

function ImportExcel() {
  const [message, setMessage] = useState('')
  const [loading, setLoading] = useState(false)

  const handleFichier = async (e) => {
    const fichier = e.target.files[0]
    if (!fichier) return

    const buffer = await fichier.arrayBuffer()
    const workbook = XLSX.read(buffer)
    const sheet = workbook.Sheets[workbook.SheetNames[0]]
    const donnees = XLSX.utils.sheet_to_json(sheet)

    // donnees = tableau d'objets avec les colonnes du fichier Excel

    setLoading(true)
    let succes = 0
    let erreurs = 0

    for (const item of donnees) {
      try {
        await produitService.create(item)
        succes++
      } catch (err) {
        erreurs++
        console.error('Erreur :', item, err.response?.data)
      }
    }

    setLoading(false)
    setMessage(`Import terminé : ${succes} créés, ${erreurs} erreurs`)
  }

  return (
    <div>
      <h3>Importer Excel</h3>
      <input type="file" accept=".xlsx,.xls" onChange={handleFichier} />
      {loading && <p>Import en cours...</p>}
      {message && <p>{message}</p>}
    </div>
  )
}

export default ImportExcel
```

---

### B3. Import JSON → API création

```jsx
// src/components/ImportJSON.jsx

import { useState } from 'react'
import produitService from '../services/produitService'

function ImportJSON() {
  const [message, setMessage] = useState('')

  const handleFichier = async (e) => {
    const fichier = e.target.files[0]
    if (!fichier) return

    const texte = await fichier.text()
    const donnees = JSON.parse(texte)
    const liste = Array.isArray(donnees) ? donnees : [donnees]

    let succes = 0
    for (const item of liste) {
      try {
        await produitService.create(item)
        succes++
      } catch (err) {
        console.error('Erreur :', err.response?.data)
      }
    }
    setMessage(`${succes} éléments importés`)
  }

  return (
    <div>
      <input type="file" accept=".json" onChange={handleFichier} />
      {message && <p>{message}</p>}
    </div>
  )
}

export default ImportJSON
```

---

## PARTIE C — Écrire des données dans des fichiers locaux

> Le navigateur ne peut pas écrire directement dans le système de fichiers.
> On génère un fichier et on force le téléchargement.

### C1. Écrire du texte brut dans un fichier

```js
export function ecrireFichierTexte(contenu, nomFichier = 'data.txt') {
  const blob = new Blob([contenu], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const lien = document.createElement('a')
  lien.href = url
  lien.download = nomFichier
  lien.click()
  URL.revokeObjectURL(url)
}
```

### C2. Exemple complet dans un composant

```jsx
import { exportCSV, exportJSON } from '../utils/exportUtils'

function ActionsPage() {
  const data = [
    { id: 1, nom: 'Alice', email: 'alice@test.com' },
    { id: 2, nom: 'Bob',   email: 'bob@test.com' },
  ]

  return (
    <div>
      <button onClick={() => exportCSV(data, 'users.csv')}>CSV</button>
      <button onClick={() => exportJSON(data, 'users.json')}>JSON</button>
    </div>
  )
}
```

---

## Récap

| Action         | Méthode                                 |
|----------------|-----------------------------------------|
| Export CSV     | `Blob` + `URL.createObjectURL`          |
| Export JSON    | `Blob` + `JSON.stringify`               |
| Export Excel   | librairie `xlsx`                        |
| Import CSV     | `fichier.text()` + `split('\n')`        |
| Import Excel   | librairie `xlsx` + `sheet_to_json`      |
| Import JSON    | `fichier.text()` + `JSON.parse`         |
| Écrire fichier | Générer un blob → forcer téléchargement |
