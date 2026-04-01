"""
install.py — Installation du module BON dans un venv isolé
Lancez : python install.py
Compatible Windows / Linux / macOS
"""
import sys
import subprocess
import pathlib
import platform

ROOT = pathlib.Path(__file__).parent
VENV_DIR = ROOT / ".venv"
REQ_FILE = ROOT / "requirements.txt"


def run(cmd: list, **kwargs) -> int:
    """Exécute une commande et affiche sa sortie en temps réel."""
    print(f"\n→ {' '.join(str(c) for c in cmd)}")
    result = subprocess.run(cmd, **kwargs)
    return result.returncode


def python_executable() -> pathlib.Path:
    """Retourne l'exécutable Python du venv créé."""
    if sys.platform == "win32":
        return VENV_DIR / "Scripts" / "python.exe"
    return VENV_DIR / "bin" / "python"


def pip_executable() -> pathlib.Path:
    """Retourne pip du venv créé."""
    if sys.platform == "win32":
        return VENV_DIR / "Scripts" / "pip.exe"
    return VENV_DIR / "bin" / "pip"


def main():
    print("=" * 55)
    print("  BON — Installation du module Facebook Publisher")
    print("=" * 55)
    print(f"OS          : {platform.system()} {platform.release()}")
    print(f"Python host : {sys.version}")
    print(f"Répertoire  : {ROOT}")

    # 1. Créer le venv si inexistant
    if not VENV_DIR.exists():
        print("\n[1/4] Création du venv...")
        rc = run([sys.executable, "-m", "venv", str(VENV_DIR)])
        if rc != 0:
            print("✗ Échec de création du venv.")
            sys.exit(1)
        print("✓ Venv créé.")
    else:
        print(f"\n[1/4] Venv déjà présent : {VENV_DIR}")

    py = python_executable()
    pip = pip_executable()

    # 2. Mettre à jour pip
    print("\n[2/4] Mise à jour de pip...")
    run([str(py), "-m", "pip", "install", "--upgrade", "pip", "--quiet"])

    # 3. Installer les dépendances
    print("\n[3/4] Installation des dépendances (requirements.txt)...")
    rc = run([str(pip), "install", "-r", str(REQ_FILE)])
    if rc != 0:
        print("✗ Échec de l'installation des dépendances.")
        sys.exit(1)
    print("✓ Dépendances installées.")

    # 4. Installer les navigateurs Playwright
    print("\n[4/4] Installation des navigateurs Playwright (Chromium)...")
    rc = run([str(py), "-m", "playwright", "install", "chromium"])
    if rc != 0:
        print("✗ Échec de l'installation de Playwright.")
        sys.exit(1)
    print("✓ Playwright Chromium installé.")

    # Résumé
    print("\n" + "=" * 55)
    print("  ✓ Installation terminée avec succès !")
    print("=" * 55)
    print("\nProchaines étapes :")
    print(f"  1. Activez le venv :")
    if sys.platform == "win32":
        print(f"       {VENV_DIR}\\Scripts\\activate")
    else:
        print(f"       source {VENV_DIR}/bin/activate")
    print(f"  2. Créez votre première session :")
    print(f"       python -m bon login --session <nom_du_compte>")
    print(f"  3. Lancez la publication :")
    print(f"       python -m bon post --session <nom_du_compte>")
    print(f"\n  Pour lister les sessions : python -m bon list-sessions")


if __name__ == "__main__":
    main()
