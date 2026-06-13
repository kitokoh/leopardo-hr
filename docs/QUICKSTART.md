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

1.  Navigate to the mobile directory (e.g., `front/mobile`).
2.  Run `flutter pub get`.
3.  Launch with `flutter run`.

---

## 📚 Next Steps

- Explore the **[API Reference](api/README.md)**.
- Understand the **[System Design](architecture/SYSTEM_DESIGN.md)**.
- Check the **[Contribution Guidelines](contributing/GUIDELINES.md)**.
