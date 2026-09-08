# Voting System API

API RESTful en PHP 8.2 para gestionar votantes, candidatos, emisión única de votos y estadísticas de resultados.

## Características

- CRUD básico de votantes.
- CRUD básico de candidatos.
- Registro de votos con transacción de base de datos.
- Un votante solo puede votar una vez.
- Un votante no puede registrarse como candidato y viceversa.
- Estadísticas con total y porcentaje de votos por candidato.
- Paginación y búsqueda en listados de votantes y candidatos.
- Documentación OpenAPI en `docs/openapi.yaml`.
- Colección Postman en `docs/postman_collection.json`.

> Nota sobre la restricción votante/candidato: el modelo solicitado no incluye documento de identidad ni email para candidatos. Por eso la API valida esta regla comparando el nombre de la persona sin distinguir mayúsculas/minúsculas.

## Requisitos

- PHP 8.2 o superior.
- Composer.
- Docker y Docker Compose.

## Instalación

```bash
composer install
cp .env.example .env
docker compose up -d
composer migrate
composer seed
composer start
```

La API queda disponible en:

```text
http://localhost:8000
```

## Endpoints

### Votantes

```http
POST   /voters
GET    /voters
GET    /voters/{id}
DELETE /voters/{id}
```

### Candidatos

```http
POST   /candidates
GET    /candidates
GET    /candidates/{id}
DELETE /candidates/{id}
```

### Votos

```http
POST /votes
GET  /votes
GET  /votes/statistics
```

## Ejemplos con curl

Crear candidato:

```bash
curl -X POST http://localhost:8000/candidates \
  -H "Content-Type: application/json" \
  -d '{"name":"Ana Torres","party":"Partido Azul"}'
```

Crear votante:

```bash
curl -X POST http://localhost:8000/voters \
  -H "Content-Type: application/json" \
  -d '{"name":"Juan Perez","email":"juan.perez@example.com"}'
```

Listar votantes con paginación y búsqueda:

```bash
curl "http://localhost:8000/voters?page=1&limit=10&search=juan"
```

Emitir voto:

```bash
curl -X POST http://localhost:8000/votes \
  -H "Content-Type: application/json" \
  -d '{"voter_id":1,"candidate_id":1}'
```

Consultar estadísticas:

```bash
curl http://localhost:8000/votes/statistics
```

Respuesta esperada:

```json
{
  "success": true,
  "data": {
    "total_votes": 1,
    "total_voters_that_voted": 1,
    "results": [
      {
        "candidate_id": 1,
        "name": "Ana Torres",
        "party": "Partido Azul",
        "votes": 1,
        "percentage": 100
      }
    ]
  }
}
```

También hay un ejemplo guardado en:

```text
docs/statistics-example.json
```

Intentar votar dos veces con el mismo votante:

```bash
curl -X POST http://localhost:8000/votes \
  -H "Content-Type: application/json" \
  -d '{"voter_id":1,"candidate_id":1}'
```

Respuesta esperada:

```json
{
  "success": false,
  "message": "This voter has already voted."
}
```

## Validaciones implementadas

- `name` obligatorio para votantes y candidatos.
- `email` obligatorio, válido y único para votantes.
- Un nombre registrado como votante no puede registrarse como candidato.
- Un nombre registrado como candidato no puede registrarse como votante.
- `voter_id` y `candidate_id` deben existir al votar.
- La emisión de voto usa transacción y bloqueo de filas con `FOR UPDATE`.
- Al votar se actualiza `voters.has_voted`.
- Al votar se incrementa `candidates.votes`.
- La tabla `votes` tiene índice único por `voter_id` como defensa adicional.

## Prueba rápida

Con el servidor corriendo:

```bash
composer smoke-test
```

Debe imprimir:

```text
[PASS] candidate created
[PASS] voter created
[PASS] vote created
[PASS] duplicate vote rejected
[PASS] statistics returned
```

## Postman

Importa la colección:

```text
docs/postman_collection.json
```

La variable `base_url` ya apunta a `http://localhost:8000`.

## Estructura

```text
public/index.php                 Front controller y definición de rutas
src/Config/Env.php               Carga simple de variables .env
src/Core/Database.php            Conexión PDO
src/Core/Router.php              Router HTTP liviano
src/Core/Request.php             Lectura de JSON, query params y método HTTP
src/Core/Response.php            Respuestas JSON consistentes
src/Controllers/*Controller.php  Lógica de endpoints
database/schema.sql              Migración de tablas
scripts/migrate.php              Ejecuta la migración
scripts/seed.php                 Datos iniciales
scripts/smoke-test.php           Prueba funcional básica
docs/openapi.yaml                Documentación Swagger/OpenAPI
docs/postman_collection.json     Colección Postman
docs/statistics-example.json     Ejemplo de estadísticas generadas
```
