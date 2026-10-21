#!/usr/bin/env python3
"""
scripts/preflight-public-cutover.py

Safe, read-only preflight cutover assessment for kasir-bangunan-app.
Evaluates production cutover readiness against target:
https://www.jajandigital.web.id/kasir-bangunan/

Strictly read-only: does not modify .env, server.js, gateway, DB, or static assets.
Never echoes credentials or secret values in output.
Fails closed with non-zero exit code when not ready for public cutover.
"""

import json
import os
import re
import sys
import urllib.request
from urllib.parse import urlsplit


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, msg, headers, newurl):
        return None


HTTP = urllib.request.build_opener(NoRedirect)

TARGET_APP_URL = "https://www.jajandigital.web.id/kasir-bangunan/"
TARGET_SUBPATH = "/kasir-bangunan"
BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
ENV_PATH = os.path.join(BASE_DIR, ".env")
APP_SERVICE_PROVIDER = os.path.join(BASE_DIR, "app", "Providers", "AppServiceProvider.php")
GATEWAY_SERVER_JS = "/home/ubuntu/penelitian-pola-pikir-ai/server.js"
STATIC_DEMO_PATH = "/home/ubuntu/penelitian-pola-pikir-ai/public/kasir-bangunan/index.html"


def parse_env_file(filepath):
    """Parses .env file into key-value pairs without exposing values to stdout."""
    env = {}
    if not os.path.isfile(filepath):
        return env
    try:
        with open(filepath, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                k, v = line.split("=", 1)
                env[k.strip()] = v.strip().strip("\"'")
    except Exception:
        pass
    return env


def check_env_readiness(env):
    """Evaluates .env configuration for public cutover without leaking secrets."""
    app_url = env.get("APP_URL", "")
    target_norm = TARGET_APP_URL.rstrip("/")
    app_url_norm = app_url.rstrip("/")
    app_url_matches = (app_url_norm == target_norm)

    db_connection = env.get("DB_CONNECTION", "").lower()
    # Production cutover requires dedicated MariaDB/MySQL rather than local dev SQLite
    db_name = env.get("DB_DATABASE", "")
    db_target_valid = db_connection in ("mariadb", "mysql") and bool(db_name) and db_name != "db_pbj"

    session_path = env.get("SESSION_PATH", "")
    session_path_scoped = session_path.rstrip("/") == TARGET_SUBPATH.rstrip("/")

    session_secure = env.get("SESSION_SECURE_COOKIE", "").lower() in ("true", "1")
    session_cookie = env.get("SESSION_COOKIE", "")
    session_scope_valid = session_path_scoped and session_secure and bool(session_cookie) and session_cookie != "laravel_session"

    return {
        "app_url_matches_target": bool(app_url_matches),
        "app_url_configured": bool(app_url),
        "db_target_type": db_connection if db_connection in ("sqlite", "mariadb", "mysql") else "undefined",
        "db_target_production_ready": bool(db_target_valid),
        "session_path_scoped": bool(session_path_scoped),
        "session_secure_cookie_configured": bool(session_secure),
        "session_scope_valid": bool(session_scope_valid),
    }


def check_server_proxy_order(server_js_path):
    """
    Checks gateway server.js for /kasir-bangunan proxy configuration
    and ensures proxy middleware is declared before static file fallback.
    """
    if not os.path.isfile(server_js_path):
        return {
            "gateway_server_file_exists": False,
            "kasir_proxy_defined": False,
            "kasir_proxy_before_static": False,
        }

    try:
        with open(server_js_path, "r", encoding="utf-8") as f:
            content = f.read()

        # Source inspection is indicative; a live HTTP probe is still required.
        route = re.search(r"app\.use\(\s*['\"]/kasir-bangunan['\"]\s*,\s*createProxyMiddleware\(", content)
        has_proxy = bool(route)
        proxy_pos = route.start() if route else -1
        static_pos = content.find("express.static(PUBLIC")
        proxy_before_static = has_proxy and static_pos != -1 and proxy_pos < static_pos

        return {
            "gateway_server_file_exists": True,
            "kasir_proxy_defined": bool(has_proxy),
            "kasir_proxy_before_static": bool(proxy_before_static),
        }
    except Exception:
        return {
            "gateway_server_file_exists": False,
            "kasir_proxy_defined": False,
            "kasir_proxy_before_static": False,
        }


def check_static_demo_marker(demo_path):
    """
    Checks exact marker /home/ubuntu/penelitian-pola-pikir-ai/public/kasir-bangunan/index.html.
    A retained static demo is a rollback source; live routing is checked separately.
    """
    exists = os.path.isfile(demo_path)
    readable = False
    size = 0
    if exists:
        try:
            readable = os.access(demo_path, os.R_OK)
            size = os.path.getsize(demo_path)
        except Exception:
            pass

    return {
        "static_demo_path": demo_path,
        "static_demo_exists": bool(exists),
        "static_demo_readable": bool(readable),
        "static_demo_bytes": size,
        "static_demo_active": bool(exists),
    }


def check_app_source_routes(provider_path):
    """Verifies that Laravel AppServiceProvider enforces rootUrl and https scheme."""
    if not os.path.isfile(provider_path):
        return {
            "provider_file_exists": False,
            "forces_root_url": False,
            "forces_https_scheme": False,
            "subpath_provider_ready": False,
        }

    try:
        with open(provider_path, "r", encoding="utf-8") as f:
            code = f.read()

        has_root_url = "forceRootUrl" in code
        has_scheme = "forceScheme" in code

        return {
            "provider_file_exists": True,
            "forces_root_url": bool(has_root_url),
            "forces_https_scheme": bool(has_scheme),
            "subpath_provider_ready": bool(has_root_url and has_scheme),
        }
    except Exception:
        return {
            "provider_file_exists": False,
            "forces_root_url": False,
            "forces_https_scheme": False,
            "subpath_provider_ready": False,
        }


def check_upstream_readiness(env):
    """Probe an explicitly configured dedicated loopback Laravel upstream, not APP_URL."""
    result = {"upstream_check_attempted": False, "upstream_ready": False}
    try:
        url = urlsplit(env.get("KASIR_UPSTREAM_URL", ""))
        if url.scheme != "http" or url.hostname != "127.0.0.1" or not url.port or url.path not in ("", "/"):
            return result
        # The known PBJ upstream and disposable preview must not count as production.
        if url.port in (8087, 18731):
            return result
        result["upstream_check_attempted"] = True
        request = urllib.request.Request(f"http://127.0.0.1:{url.port}/login")
        with HTTP.open(request, timeout=2) as response:
            result["upstream_ready"] = (response.status == 200 and response.geturl() == request.full_url and b"LOGIN KASIR" in response.read(16384))
    except (ValueError, OSError, TimeoutError):
        pass
    return result


def check_public_route():
    """Only a real Laravel login page at the public path can pass this gate."""
    try:
        request = urllib.request.Request(TARGET_APP_URL + "login")
        with HTTP.open(request, timeout=5) as response:
            body = response.read(16384)
            return response.status == 200 and response.geturl() == request.full_url and b"LOGIN KASIR" in body
    except (OSError, TimeoutError):
        return False


def check_db_isolation(env):
    """Configuration guard, NOT a database connectivity/permission audit."""
    name = env.get("DB_DATABASE", "")
    host = env.get("DB_HOST", "")
    return bool(re.fullmatch(r"kasir_bangunan_[a-z0-9_]+", name)) and host in ("127.0.0.1", "localhost")


def check_rollback_snapshot():
    """Require a separately created snapshot, not merely a writable source."""
    directory = os.path.join(BASE_DIR, "backups")
    try:
        return any(
            re.fullmatch(r"kasir-bangunan-static-[0-9]{8}\.html", name)
            and os.path.getsize(os.path.join(directory, name)) > 0
            for name in os.listdir(directory)
        )
    except OSError:
        return False


def check_assets():
    return os.path.isfile(os.path.join(BASE_DIR, "public", "build", "manifest.json"))


def check_debug_disabled(env):
    return env.get("APP_ENV") == "production" and env.get("APP_DEBUG", "").lower() in ("false", "0")


def check_backup_plan_feasibility(demo_path):
    """
    Checks read-only feasibility of rollback backup plan for static demo marker.
    Verifies source readability, target parent write permissions, and disk space.
    Does NOT create any archive or touch files.
    """
    source_exists = os.path.isfile(demo_path)
    parent_dir = os.path.dirname(demo_path)
    parent_writable = os.access(parent_dir, os.W_OK) if os.path.isdir(parent_dir) else False

    disk_free_mb = 0
    try:
        stat = os.statvfs(parent_dir if os.path.isdir(parent_dir) else "/")
        disk_free_mb = (stat.f_bavail * stat.f_frsize) // (1024 * 1024)
    except Exception:
        pass

    feasible = source_exists and parent_writable and (disk_free_mb > 50)

    return {
        "target_source_path": demo_path,
        "source_exists": bool(source_exists),
        "target_parent_writable": bool(parent_writable),
        "disk_free_mb": disk_free_mb,
        "backup_plan_feasible": bool(feasible),
    }


def main():
    env = parse_env_file(ENV_PATH)

    env_checks = check_env_readiness(env)
    server_checks = check_server_proxy_order(GATEWAY_SERVER_JS)
    demo_checks = check_static_demo_marker(STATIC_DEMO_PATH)
    app_checks = check_app_source_routes(APP_SERVICE_PROVIDER)
    upstream_checks = check_upstream_readiness(env)
    backup_checks = check_backup_plan_feasibility(STATIC_DEMO_PATH)

    # Readiness condition: All critical cutover gating conditions must pass.
    # Currently expected to FAIL (NO_GO) because system is in pre-cutover state:
    # 1. APP_URL points to localhost, not https://www.jajandigital.web.id/kasir-bangunan/
    # 2. DB is dev sqlite, not production mariadb/mysql
    # 3. Gateway server.js does not yet proxy /kasir-bangunan before static
    # 4. Static demo is still active at /home/ubuntu/penelitian-pola-pikir-ai/public/kasir-bangunan/index.html
    # 5. Upstream dedicated daemon is not ready/bound for public traffic
    ready = (
        env_checks["app_url_matches_target"]
        and env_checks["db_target_production_ready"]
        and env_checks["session_scope_valid"]
        and check_db_isolation(env)
        and check_debug_disabled(env)
        and check_assets()
        and check_rollback_snapshot()
        and server_checks["kasir_proxy_defined"]
        and server_checks["kasir_proxy_before_static"]
        and demo_checks["static_demo_readable"]
        and app_checks["subpath_provider_ready"]
        and upstream_checks["upstream_ready"]
        and check_public_route()
    )

    result = {
        "status": "READY_FOR_CUTOVER" if ready else "NOT_READY_NO_GO",
        "ready": bool(ready),
        "target_url": TARGET_APP_URL,
        "checks": {
            "env_app_url_matches": env_checks["app_url_matches_target"],
            "env_db_target_ready": env_checks["db_target_production_ready"],
            "env_db_target_type": env_checks["db_target_type"],
            "env_db_config_isolated": check_db_isolation(env),
            "env_debug_disabled": check_debug_disabled(env),
            "env_session_scope_valid": env_checks["session_scope_valid"],
            "server_proxy_defined": server_checks["kasir_proxy_defined"],
            "server_proxy_before_static": server_checks["kasir_proxy_before_static"],
            "static_demo_marker_active": demo_checks["static_demo_active"],
            "static_demo_marker_path": demo_checks["static_demo_path"],
            "app_subpath_provider_ready": app_checks["subpath_provider_ready"],
            "upstream_ready": upstream_checks["upstream_ready"],
            "public_laravel_login": check_public_route(),
            "assets_manifest_present": check_assets(),
            "rollback_snapshot_exists": check_rollback_snapshot(),
            "backup_plan_feasible": backup_checks["backup_plan_feasible"],
        },
    }

    # Output strict JSON to stdout without echoing secrets
    print(json.dumps(result, indent=2))

    # Exit non-zero when not ready (fails closed)
    if not ready:
        sys.exit(1)
    sys.exit(0)


if __name__ == "__main__":
    main()
