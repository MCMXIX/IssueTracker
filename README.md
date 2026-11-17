# Issue Tracker

A Laravel 13 application with a Vue 3 and TypeScript frontend bundled by Vite.
Laravel serves the Vue entry page through Blade. Ticket features will use the
same-origin JSON API.

## Local setup

Start Docker Desktop or a Docker Engine with the Compose plugin. From the project
root, copy the environment template and build the application image.

```sh
cp .env.example .env
docker compose build app
docker compose run --rm --no-deps app php artisan key:generate --show
```

Paste the generated value into `APP_KEY` in `.env`, then run:

```sh
docker compose up -d --wait
docker compose exec app php artisan migrate --seed
```

The default local addresses are:

- Vue entry page: `http://localhost:8000`
- Laravel health check: `http://localhost:8000/up`
- Horizon dashboard: `http://localhost:8000/horizon`

Sign in locally with `demo@example.com` and `issue-tracker-demo`. The seeder
creates this account only in the local environment. The Vue app obtains a CSRF
cookie from `GET /sanctum/csrf-cookie`, starts a session through `POST /login`,
reads the signed-in user from `GET /api/me`, and ends the session with
`POST /logout`.

Check the worker and run the scaffold tests from the running app:

```sh
docker compose exec app php artisan horizon:status
docker compose exec app composer test
```

To start phpMyAdmin, run `docker compose --profile tools up -d phpmyadmin`.
Open `http://localhost:8081` and sign in with the database username and password
from `.env`. Both published ports are bound to loopback. MySQL and Redis have
named volumes and are reachable by service name inside Compose. MySQL stores
application data and Redis is the Horizon queue backend.

After changing application code, run `docker compose up -d --build --wait` to
rebuild the shared app and worker image. Use `docker compose down` to stop the
stack while retaining the volumes.

## Code structure

| Path | Responsibility |
| --- | --- |
| `routes/web.php`, `resources/views/app.blade.php` | Serve the Vue entry page. |
| `resources/js/` | Mount the Vue app and hold issue pages as they are built. |
| `routes/api.php`, `app/Http/` | Register JSON endpoints, validate input, and serialize responses. |
| `app/Actions/Issues/` | Create and update issues and add comments. |
| `app/Models/`, `app/Queries/` | Model relationships and assemble shared read queries. |
| `app/Jobs/`, `app/Services/IssueSummary/` | Run summary generation in Horizon and encapsulate its providers. |

The setup milestone includes the web shell and infrastructure. The first feature
milestone adds the issue schema and JSON endpoints.
