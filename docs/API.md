# Documentation API

Toutes les réponses sont en JSON. Les erreurs suivent systématiquement le format :

```json
{
    "error": {
        "code": "SOME_CODE",
        "message": "Human readable message",
        "details": []
    }
}
```

## Authentification

Les routes d'écriture (`POST /api/interventions`, `PATCH /api/interventions/{id}/close`)
nécessitent l'en-tête `X-API-KEY` (valeur définie par la variable d'environnement `API_KEY`).
La lecture (`GET /api/antennas`) est publique.

---

## `GET /api/antennas`

Liste paginée des antennes, avec la dernière intervention connue de chacune.

**Query params**

| Paramètre | Type   | Défaut | Description               |
| --------- | ------ | ------ | ------------------------- |
| `city`    | string | —      | Filtre exact sur la ville |
| `status`  | string | —      | `UP` ou `DOWN`            |
| `page`    | int    | 1      | Numéro de page (1-indexé) |
| `limit`   | int    | 20     | Taille de page (max 100)  |

**Exemple**

```bash
curl "http://localhost:8000/api/antennas?city=Paris&status=DOWN&page=1&limit=20"
```

**Réponse `200`**

```json
{
    "data": [
        {
            "id": 42,
            "name": "Antenne Paris 12",
            "city": "Paris",
            "status": "DOWN",
            "created_at": "2024-01-15T10:00:00+00:00",
            "last_intervention": {
                "id": 7,
                "description": "Panne secteur suite orage.",
                "technician_identity": "mkhaled",
                "priority": "HIGH",
                "created_at": "2024-05-02T08:30:00+00:00",
                "ended_at": null
            }
        }
    ],
    "meta": { "page": 1, "limit": 20, "total": 1523 }
}
```

---

## `POST /api/interventions`

Crée une intervention et bascule automatiquement l'antenne concernée à `DOWN`.

**Règle métier** : une seule intervention active par antenne. Toute tentative de
création alors qu'une intervention est déjà active sur cette antenne renvoie `409`.

**Body**

```json
{
    "antenna_id": 42,
    "description": "Panne secteur suite orage.",
    "technician_identity": "mkhaled",
    "priority": "HIGH"
}
```

**Exemple**

```bash
curl -X POST http://localhost:8000/api/interventions -H "Content-Type: application/json" -H "X-API-KEY: super-secret-api-key-change-me" -d '{"antenna_id":42,"description":"Panne secteur suite orage.","technician_identity":"mkhaled","priority":"HIGH"}'
```

**Réponses**

- `201 Created` — intervention créée, avec sa représentation JSON.
- `404 Not Found` — l'antenne n'existe pas.
- `409 Conflict` (`ACTIVE_INTERVENTION_EXISTS`) — une intervention active existe déjà sur cette antenne.
- `422 Unprocessable Entity` (`VALIDATION_ERROR`) — champ manquant ou invalide (détails par champ dans `error.details`).
- `401 Unauthorized` — en-tête `X-API-KEY` manquant ou invalide.

---

## `PATCH /api/interventions/{id}/close`

Clôture une intervention active et repasse l'antenne à `UP`.

**Exemple**

```bash
curl -X PATCH http://localhost:8000/api/interventions/7/close -H "X-API-KEY: super-secret-api-key-change-me"
```

**Réponses**

- `200 OK` — intervention clôturée, avec sa représentation JSON (`ended_at` renseigné).
- `404 Not Found` — l'intervention n'existe pas.
- `409 Conflict` (`INTERVENTION_ALREADY_CLOSED`) — l'intervention est déjà clôturée.
- `409 Conflict` (`CONCURRENT_MODIFICATION`) — deux clôtures concurrentes sur la même intervention.
- `401 Unauthorized` — en-tête `X-API-KEY` manquant ou invalide.
