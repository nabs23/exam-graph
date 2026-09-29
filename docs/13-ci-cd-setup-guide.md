# CI/CD Setup Guide: GitHub, Docker Hub, and Coolify

This guide explains how to deploy ExamGraph using the repository's current
GitHub Actions, Docker, Docker Hub, and Coolify configuration.

## Deployment flow

```text
Pull request
    │
    └── GitHub Actions: tests.yml
            ├── PHP 8.4 and Composer setup
            ├── Node 22 and frontend setup
            └── lint, static analysis, TypeScript checks, and tests

Merge or push to main
    │
    └── GitHub Actions: deploy.yml
            ├── Build the production Docker image
            ├── Push <Docker Hub user>/exam-graph:latest
            └── Call the Coolify deployment webhook
                    │
                    └── Coolify pulls and runs the new image
```

The deployment workflow runs on every push to `main`. The test workflow runs
on pull requests and pushes to `main`.

## Prerequisites

Prepare the following accounts and resources:

1. A GitHub repository containing ExamGraph.
2. A Docker Hub account and repository named `exam-graph`.
3. A Coolify server with access to the Docker Hub image.
4. A production database. The default application configuration uses SQLite,
   but a persistent production database such as MySQL or PostgreSQL is
   recommended.

## Docker Hub setup

1. Create a Docker Hub repository named `exam-graph`.
2. Create a Docker Hub access token with permission to push images.
3. Record the Docker Hub username and token for GitHub Actions.

The workflow publishes these two tags:

- `${DOCKERHUB_USERNAME}/exam-graph:latest` — the deployable application image.
- `${DOCKERHUB_USERNAME}/exam-graph:buildcache` — BuildKit cache layers used to
  speed up later builds.

The `latest` tag is overwritten on each successful push to `main`.

## Coolify setup

Create a new Coolify application configured to use a Docker image.

### Image settings

Use the following values:

| Setting | Value |
| --- | --- |
| Image | `<Docker Hub username>/exam-graph:latest` |
| Registry | Docker Hub |
| Container port | `8080` |
| Health endpoint | `/up` |

The image contains Nginx, PHP-FPM, and Supervisor. Nginx listens on port
`8080`; do not configure Coolify to use the PHP-FPM port `9000` directly.

If the Docker Hub repository is private, add Docker Hub registry credentials
to Coolify before deploying the application.

### Deployment webhook

In Coolify, create or locate the deploy webhook for the ExamGraph application
and copy its URL. The URL is stored in GitHub as
`COOLIFY_WEBHOOK_EXAM_GRAPH`.

The webhook must deploy the application after the `latest` image is pushed.
The workflow sends the Coolify token as a bearer token, so configure the
corresponding token in GitHub as `COOLIFY_TOKEN`.

### Production environment variables

Configure these values in Coolify's application environment settings:

```dotenv
APP_NAME=ExamGraph
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:<generated-production-key>
APP_URL=https://<examgraph-domain>

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=<database-host>
DB_PORT=3306
DB_DATABASE=<database-name>
DB_USERNAME=<database-user>
DB_PASSWORD=<database-password>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

VITE_APP_NAME=ExamGraph
```

Adjust the database variables for PostgreSQL if that is the selected
production database. Keep `APP_KEY` stable across deployments; changing it
invalidates encrypted cookies and other encrypted application data.

If local uploads or generated files must survive container replacement, attach
persistent storage for the relevant Laravel storage directory or use an
external object-storage disk.

## GitHub Actions secrets

Add these repository secrets under **Settings → Secrets and variables →
Actions**:

| Secret | Purpose |
| --- | --- |
| `DOCKERHUB_USERNAME` | Docker Hub account or organization name. |
| `DOCKERHUB_TOKEN` | Docker Hub access token with image push permission. |
| `COOLIFY_TOKEN` | Bearer token accepted by the Coolify deployment webhook. |
| `COOLIFY_WEBHOOK_EXAM_GRAPH` | Coolify deploy webhook URL for ExamGraph. |

No Vite or Reverb secret is required. The deployment image builds the frontend
with `VITE_APP_NAME=ExamGraph`.

## Enable CI protection

For the test workflow to gate deployments through normal team workflow:

1. Require pull requests for changes to `main`.
2. Add the `ci` job from `tests.yml` as a required status check.
3. Require the branch to be up to date before merging, if desired.
4. Merge only after the CI check passes.

The deploy workflow itself is triggered by a push to `main`, so direct pushes
to `main` will start deployment even though the test and deploy workflows run
independently. Branch protection is therefore the mechanism that prevents an
unverified pull request from reaching deployment.

## First deployment checklist

1. Create the Docker Hub repository and access token.
2. Create the Coolify application using the Docker image settings above.
3. Configure the production environment variables in Coolify.
4. Run the initial database migrations from Coolify before serving traffic:

   ```bash
   php artisan migrate --force
   ```

5. Add the four GitHub Actions secrets.
6. Push a test branch and open a pull request.
7. Confirm the `ci` job passes.
8. Merge to `main`.
9. Confirm the deploy workflow:
   - builds the Docker image;
   - pushes `latest` to Docker Hub; and
   - receives a successful response from the Coolify webhook.
10. Confirm the Coolify deployment is healthy and open `/up` on the deployed
    application.

## Troubleshooting

### Docker Hub login fails

Check that `DOCKERHUB_USERNAME` is the correct account name and that
`DOCKERHUB_TOKEN` is an access token, not the Docker Hub account password. The
token must be allowed to push to the `exam-graph` repository.

### Coolify webhook fails

Verify that the webhook belongs to the correct Coolify application, the token
has not expired, and the Coolify application is configured to pull the exact
image name used by the workflow.

### The application starts but returns a database error

Check the database variables in Coolify and run `php artisan migrate --force`.
The container entrypoint optimizes the Laravel application but does not run
database migrations automatically.

### Assets are missing

Review the Docker build logs for the Wayfinder and Vite steps. The frontend
build requires Node 22, PHP 8.4, the Composer vendor directory, and the
Laravel `artisan` file; these are included in the current Dockerfile.
