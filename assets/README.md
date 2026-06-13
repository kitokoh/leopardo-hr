# Visual Assets Directory — Leopardo RH

This directory contains high-quality visual assets for documentation, marketing, and developer onboarding.

## 📁 Directory Structure

- 🖼 **[/screenshots/](screenshots/)** — High-resolution captures of the Web and Mobile interfaces.
- 📊 **[/diagrams/](diagrams/)** — Architecture, RBAC, and Workflow diagrams (SVG/PNG).
- 🎞 **[/videos/](videos/)** — Product demos and feature walkthroughs.
- 🏴 **[/banners/](banners/)** — Repository banners and social preview images.

## 🏗 Key Diagrams

### RBAC System Flow
```mermaid
graph TD
    User[User Session] --> Role{Has Role?}
    Role -- SuperAdmin --> Full[All Tenants & Platform]
    Role -- Manager --> Tenant[Tenant Data + Approvals]
    Role -- Employee --> Self[Personal Data + Attendance]
    Role -- Finance --> Pay[Payroll & Reports]
```

### AI Orchestration
```mermaid
graph LR
    API[Laravel API] --> Queue[Redis Queue]
    Queue --> Worker[AI Worker]
    Worker --> LLM[Claude/GPT-4]
    LLM --> Worker
    Worker --> DB[(Database)]
    DB --> UI[Dashboard]
```

## 📸 Branding Assets

Official icons and splash screens can be found in `docs/assets/mobile-branding/`.
