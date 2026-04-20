# CRUD COMPLET REACT

## Objectif : une page avec liste + formulaire + modifier + supprimer

---

## 1. Le service — `src/services/produitService.js`

```js
import api from './api'

const produitService = {
  getAll:  ()         => api.get('/produits/'),
  getById: (id)       => api.get(`/produits/${id}/`),
  create:  (data)     => api.post('/produits/', data),
  update:  (id, data) => api.put(`/produits/${id}/`, data),
  delete:  (id)       => api.delete(`/produits/${id}/`),
}

export default produitService
```

---

## 2. Le formulaire — `src/components/ProduitForm.jsx`

```jsx
import { useState, useEffect } from 'react'

function ProduitForm({ produit, onSubmit, onCancel }) {
  const [form, setForm] = useState({ nom: '', prix: '', stock: '' })

  // Si on édite, pré-remplir le formulaire
  useEffect(() => {
    if (produit) {
      setForm({ nom: produit.nom, prix: produit.prix, stock: produit.stock })
    }
  }, [produit])

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    onSubmit(form)
  }

  return (
    <form onSubmit={handleSubmit}>
      <input
        name="nom"
        value={form.nom}
        onChange={handleChange}
        placeholder="Nom du produit"
        required
      />
      <input
        name="prix"
        type="number"
        value={form.prix}
        onChange={handleChange}
        placeholder="Prix"
        required
      />
      <input
        name="stock"
        type="number"
        value={form.stock}
        onChange={handleChange}
        placeholder="Stock"
      />
      <button type="submit">{produit ? 'Modifier' : 'Créer'}</button>
      {onCancel && <button type="button" onClick={onCancel}>Annuler</button>}
    </form>
  )
}

export default ProduitForm
```

---

## 3. La liste — `src/components/ProduitList.jsx`

```jsx
function ProduitList({ produits, onEdit, onDelete }) {
  return (
    <table border="1">
      <thead>
        <tr>
          <th>ID</th><th>Nom</th><th>Prix</th><th>Stock</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        {produits.map(p => (
          <tr key={p.id}>
            <td>{p.id}</td>
            <td>{p.nom}</td>
            <td>{p.prix} €</td>
            <td>{p.stock}</td>
            <td>
              <button onClick={() => onEdit(p)}>Modifier</button>
              <button onClick={() => onDelete(p.id)}>Supprimer</button>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

export default ProduitList
```

---

## 4. La page principale — `src/pages/ProduitsPage.jsx`

```jsx
import { useState, useEffect } from 'react'
import produitService from '../services/produitService'
import ProduitForm from '../components/ProduitForm'
import ProduitList from '../components/ProduitList'

function ProduitsPage() {
  const [produits, setProduits]     = useState([])
  const [selected, setSelected]     = useState(null)  // produit en cours d'édition
  const [showForm, setShowForm]     = useState(false)
  const [message, setMessage]       = useState('')
  const [loading, setLoading]       = useState(false)

  // ── READ ──────────────────────────────────────────────
  useEffect(() => { charger() }, [])

  const charger = async () => {
    setLoading(true)
    try {
      const res = await produitService.getAll()
      setProduits(res.data)
    } catch (e) {
      setMessage('Erreur lors du chargement')
    } finally {
      setLoading(false)
    }
  }

  // ── CREATE / UPDATE ───────────────────────────────────
  const handleSubmit = async (formData) => {
    try {
      if (selected) {
        await produitService.update(selected.id, formData)
        setMessage('Produit modifié !')
      } else {
        await produitService.create(formData)
        setMessage('Produit créé !')
      }
      setShowForm(false)
      setSelected(null)
      charger() // rafraîchir la liste
    } catch (e) {
      setMessage('Erreur : ' + (e.response?.data?.detail || e.message))
    }
  }

  // ── DELETE ────────────────────────────────────────────
  const handleDelete = async (id) => {
    if (!window.confirm('Supprimer ce produit ?')) return
    try {
      await produitService.delete(id)
      setMessage('Produit supprimé')
      charger()
    } catch (e) {
      setMessage('Erreur lors de la suppression')
    }
  }

  // ── EDIT ──────────────────────────────────────────────
  const handleEdit = (produit) => {
    setSelected(produit)
    setShowForm(true)
  }

  return (
    <div>
      <h1>Produits</h1>

      {message && <p style={{ color: 'green' }}>{message}</p>}

      <button onClick={() => { setShowForm(true); setSelected(null) }}>
        + Nouveau produit
      </button>

      {showForm && (
        <ProduitForm
          produit={selected}
          onSubmit={handleSubmit}
          onCancel={() => { setShowForm(false); setSelected(null) }}
        />
      )}

      {loading ? (
        <p>Chargement...</p>
      ) : (
        <ProduitList
          produits={produits}
          onEdit={handleEdit}
          onDelete={handleDelete}
        />
      )}
    </div>
  )
}

export default ProduitsPage
```

---

## 5. Brancher dans App.jsx

```jsx
import ProduitsPage from './pages/ProduitsPage'

function App() {
  return <ProduitsPage />
}

export default App
```

---

## Récap CRUD React

| Action          | Hook                           | Service            |
|-----------------|--------------------------------|--------------------|
| Lire au montage | `useEffect(() => {}, [])`      | `getAll()`         |
| Créer           | `handleSubmit`                 | `create(data)`     |
| Modifier        | `handleSubmit` (si `selected`) | `update(id, data)` |
| Supprimer       | `handleDelete(id)`             | `delete(id)`       |
| Rafraîchir      | Rappeler `charger()`           | —                  |
