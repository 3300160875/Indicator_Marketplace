# Development Commands

First start:

1. Run `make doctor` to check local prerequisites.
2. Run `make bootstrap` to copy `.env.example` to ignored `.env`, install Composer dependencies, and start Docker services.
3. Run `make test-smoke` to verify the running local services.

Common commands:

- `make up`: start Docker services.
- `make down`: stop Docker services.
- `make reset`: stop services and remove disposable local volumes.
- `make install`: run Composer install in the Docker CLI container.
- `make lint`: validate Compose, shell scripts, Composer metadata and tracked PHP syntax.
- `make status`: show Docker service status.
