# SERVICES REACT — Axios & Pattern Service

## 1. Instance Axios globale — `src/services/api.js`

```js
import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
  }
})

// Ajouter le token JWT automatiquement (si authentification)
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export default api
```

---

## 2. Service par entité — `src/services/userService.js`

```js
import api from './api'

const userService = {
  // Lire tous
  getAll: () => api.get('/users/'),

  // Lire un seul
  getById: (id) => api.get(`/users/${id}/`),

  // Créer
  create: (data) => api.post('/users/', data),

  // Modifier complet
  update: (id, data) => api.put(`/users/${id}/`, data),

  // Modifier partiel
  patch: (id, data) => api.patch(`/users/${id}/`, data),

  // Supprimer
  delete: (id) => api.delete(`/users/${id}/`),
}

export default userService
```

---

## 3. Utiliser le service dans un composant

```jsx
import { useState, useEffect } from 'react'
import userService from '../services/userService'

function UserList() {
  const [users, setUsers] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    chargerUsers()
  }, [])

  const chargerUsers = async () => {
    setLoading(true)
    try {
      const response = await userService.getAll()
      setUsers(response.data)
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur serveur')
    } finally {
      setLoading(false)
    }
  }

  if (loading) return <p>Chargement...</p>
  if (error) return <p style={{ color: 'red' }}>{error}</p>

  return (
    <ul>
      {users.map(user => (
        <li key={user.id}>{user.nom} — {user.email}</li>
      ))}
    </ul>
  )
}

export default UserList
```

---

## 4. Service avec upload fichier — `src/services/uploadService.js`

```js
import api from './api'

const uploadService = {
  // Upload un seul fichier
  upload: (endpoint, fichier) => {
    const formData = new FormData()
    formData.append('fichier', fichier)
    return api.post(endpoint, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },
}

export default uploadService
```

---

## 5. Gérer les erreurs proprement

```js
try {
  const response = await userService.create(data)
  console.log('Créé :', response.data)
} catch (err) {
  if (err.response) {
    // Erreur serveur (400, 404, 500...)
    console.error('Erreur serveur :', err.response.status, err.response.data)
  } else if (err.request) {
    // Pas de réponse (serveur down, CORS)
    console.error('Pas de réponse du serveur')
  } else {
    // Erreur dans le code
    console.error('Erreur :', err.message)
  }
}
```

---

## Résumé des méthodes axios

| Méthode | Code                                                       |
|---------|------------------------------------------------------------|
| GET     | `api.get('/users/')`                                       |
| POST    | `api.post('/users/', { nom: 'Alice' })`                    |
| PUT     | `api.put('/users/1/', { nom: 'Alice', email: 'a@a.com' })` |
| PATCH   | `api.patch('/users/1/', { nom: 'Alice' })`                 |
| DELETE  | `api.delete('/users/1/')`                                  |
