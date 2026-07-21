# Quick Start Guide — Leopardo RH

Welcome to Leopardo RH! This guide will help you get your environment set up and make your first API call in minutes.

## 🏃‍♂️ Fast Track (Docker)

1.  **Clone the Repo**
    ```bash
    git clone https://github.com/kitokoh/leopardo-hr.git
    cd leopardo-hr
    ```

2.  **Start Services**
    ```bash
    docker-compose up -d
    ```

3.  **Seed Database**
    ```bash
    docker exec -it leopardo-api php artisan migrate --seed
    ```

Your API is now live at `http://localhost:8000`.

## 🛠 Manual Setup (PHP/Laravel)

If you prefer running Laravel directly:

1.  **Install Dependencies**
    ```bash
    cd api
    composer install
    ```

2.  **Environment Configuration**
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

3.  **Run Migrations**
    ```bash
    php artisan migrate
    ```

4.  **Serve**
    ```bash
    php artisan serve
    ```

## 📱 Mobile App (Flutter)

> The legacy monolithic mobile app (`front/mobile/`) was removed from the repo (PR #754). Launch
> mobile apps now live under `front/mobile_apps/*` (see `front/mobile_apps/README.md`):
> `leopardo_core` (shared package), `leopardo_employee`, `leopardo_manager`, `leopardo_hr`,
> `leopardo_platform_admin`.

1.  Navigate to the app you want, e.g. `front/mobile_apps/leopardo_employee/`.
2.  Run `flutter pub get`.
3.  Launch with `flutter run`.

---

## 📚 Next Steps

- Explore the **[API Reference](api/README.md)**.
- Understand the **[System Architecture](architecture/ARCHITECTURE.md)**.
- Check the **[Contribution Guidelines](contributing/GUIDELINES.md)**.
