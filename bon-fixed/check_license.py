"""
check_license.py — Vérification de licence par numéro de série + adresse MAC
Cross-platform : Windows 10/11, macOS, Linux.
Corrections v4.0 :
  - MAC lue dynamiquement depuis la licence (plus de valeur hardcodée)
  - get_serial_number() utilise PowerShell en priorité sur Windows (wmic déprécié Win11 22H2+)
  - Fallback UUID machine sur macOS et Linux
"""
import os
import re
import sys
import uuid
import datetime
import subprocess
import platform


def get_license_file():
    for directory in [os.getcwd(), os.path.dirname(os.getcwd())]:
        path = os.path.join(directory, "python.txt")
        if os.path.exists(path):
            return path
    return None


def parse_license(license_str):
    match = re.match(
        r'^(A1a9)(\d{3})'
        r'([A-Za-z0-9 ]+|To be filled by O\.E\.M\.)'
        r':([A-F0-9-]{14})'
        r'(\d{12})'
        r'(\w+)$',
        license_str
    )
    if not match:
        return (None,) * 6
    prefix        = match.group(1)
    validity_days = int(match.group(2))
    serial_number = match.group(3).strip()
    mac_address   = match.group(4)
    date_str      = match.group(5)
    user_id       = match.group(6)
    try:
        license_date = datetime.datetime(
            int(date_str[8:12]), int(date_str[6:8]), int(date_str[4:6]),
            int(date_str[0:2]),  int(date_str[2:4])
        )
    except ValueError:
        return (None,) * 6
    return validity_days, prefix, serial_number, mac_address, license_date, user_id


def get_serial_number():
    """
    Cross-platform, Win11-safe.
    Windows  : PowerShell d'abord (Win10/11 22H2+), puis wmic (fallback Win10).
    macOS    : system_profiler.
    Linux    : /sys/class/dmi/id/product_serial.
    Fallback : UUID machine stable (adresse MAC en hexa).
    """
    os_name = platform.system()

    if os_name == "Windows":
        # PowerShell — compatible Windows 10 ET 11 22H2+ (wmic supprimé)
        try:
            r = subprocess.run(
                ["powershell", "-NoProfile", "-Command",
                 "(Get-WmiObject Win32_BIOS).SerialNumber"],
                capture_output=True, text=True, timeout=10
            )
            sn = r.stdout.strip()
            if sn and "To be filled" not in sn and len(sn) > 2:
                return sn
        except Exception:
            pass
        # Fallback wmic (Windows 10 uniquement)
        try:
            out = subprocess.run(
                ["wmic", "bios", "get", "serialnumber"],
                capture_output=True, text=True, timeout=10
            ).stdout
            lines = [l.strip() for l in out.splitlines() if l.strip()]
            if len(lines) >= 2 and "To be filled" not in lines[1]:
                return lines[1]
        except Exception:
            pass

    elif os_name == "Darwin":
        try:
            out = subprocess.run(
                ["system_profiler", "SPHardwareDataType"],
                capture_output=True, text=True, timeout=10
            ).stdout
            for line in out.splitlines():
                if "Serial Number" in line:
                    return line.split(":")[-1].strip()
        except Exception:
            pass

    else:  # Linux
        try:
            p = "/sys/class/dmi/id/product_serial"
            if os.path.exists(p):
                sn = open(p).read().strip()
                if sn and "To be filled" not in sn:
                    return sn
        except Exception:
            pass

    # Fallback universel : UUID dérivé de la MAC (stable par machine)
    return str(uuid.UUID(int=uuid.getnode())).upper()


def get_mac_addresses():
    """
    Retourne toutes les MACs actives au format XX-XX-XX-XX-XX (5 groupes).
    Plus de valeur hardcodée — lu dynamiquement depuis le matériel.
    """
    macs = []
    node = uuid.getnode()
    mac_hex = f"{node:012X}"
    macs.append("-".join(mac_hex[i:i+2] for i in range(0, 10, 2)))
    try:
        import psutil
        for _, addrs in psutil.net_if_addrs().items():
            for addr in addrs:
                if addr.family == psutil.AF_LINK:
                    raw = addr.address.replace(":", "-").replace(".", "-").upper()
                    parts = raw.split("-")
                    if len(parts) >= 5:
                        macs.append("-".join(parts[:5]))
    except ImportError:
        pass
    return list(dict.fromkeys(macs))


def check_mac_address(license_mac):
    """Vérifie si l'une des MACs de la machine correspond à celle de la licence."""
    for mac in get_mac_addresses():
        if mac.upper() == license_mac.upper():
            return True
    return False


def check_serial_number(license_serial):
    return get_serial_number() == license_serial


def is_license_valid():
    """Vérifie la validité complète de la licence."""
    license_file = get_license_file()
    if not license_file:
        print("[LICENCE] ✗ Fichier de licence introuvable (python.txt).", file=sys.stderr)
        return False

    try:
        with open(license_file, "r", encoding="utf-8") as f:
            content = f.readline().strip()
    except OSError as e:
        print(f"[LICENCE] ✗ Impossible de lire : {e}", file=sys.stderr)
        return False

    validity_days, prefix, serial, mac, lic_date, user_id = parse_license(content)
    if validity_days is None or prefix != "A1a9":
        print("[LICENCE] ✗ Format invalide.", file=sys.stderr)
        return False

    expiration = lic_date + datetime.timedelta(days=validity_days)
    if datetime.datetime.now() > expiration:
        print(f"[LICENCE] ✗ Expirée le {expiration.strftime('%Y-%m-%d')}.", file=sys.stderr)
        return False

    if serial != "To be filled by O.E.M." and not check_serial_number(serial):
        print("[LICENCE] ✗ Numéro de série non reconnu.", file=sys.stderr)
        return False

    if not check_mac_address(mac):
        print("[LICENCE] ✗ Adresse MAC non reconnue.", file=sys.stderr)
        return False

    print(f"[LICENCE] ✓ Valide jusqu'au {expiration.strftime('%Y-%m-%d')} — {user_id}")
    return True


if __name__ == "__main__":
    sys.exit(0 if is_license_valid() else 1)
