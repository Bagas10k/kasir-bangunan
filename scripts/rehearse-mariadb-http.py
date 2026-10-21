#!/usr/bin/env python3
"""Disposable MariaDB + real HTTP login/CSRF rehearsal. Never targets a live DB."""
import http.client
import http.server
import json
import os
import pathlib
import re
import secrets
import signal
import subprocess
import sys
import tempfile
import threading
import time
import urllib.parse

APP = pathlib.Path(__file__).resolve().parent.parent
RUN = secrets.token_hex(4)
NET = f"kasir_ephem_net_{RUN}"
DB = f"kasir_ephem_mariadb_{RUN}"
WEB = f"kasir_ephem_http_{RUN}"
DB_NAME = f"kasir_ephem_db_{RUN}"
DB_USER = f"kasir_ephem_usr_{RUN}"
DB_PASSWORD = secrets.token_urlsafe(24)
ROOT_PASSWORD = secrets.token_urlsafe(24)
LOGIN_PASSWORD = secrets.token_urlsafe(24)
OWNED = []


def command(args, *, env=None, timeout=90):
    return subprocess.run(args, capture_output=True, text=True, timeout=timeout, env=env)


def required(args, label, *, env=None, timeout=90):
    result = command(args, env=env, timeout=timeout)
    if result.returncode:
        if label == "start disposable HTTP":
            reason = result.stderr.replace(DB_PASSWORD, "[REDACTED]").replace(ROOT_PASSWORD, "[REDACTED]").replace(LOGIN_PASSWORD, "[REDACTED]")
            raise RuntimeError(f"{label}: exit {result.returncode}; {reason[:400]}")
        raise RuntimeError(f"{label}: exit {result.returncode}")
    return result.stdout


def docker_env():
    return {
        "APP_ENV": "local",  # Laravel testing environment disables CSRF.
        "APP_DEBUG": "false",
        "APP_URL": "https://example.test/kasir-bangunan",
        "DB_CONNECTION": "mysql",
        "DB_HOST": DB,
        "DB_PORT": "3306",
        "DB_DATABASE": DB_NAME,
        "DB_USERNAME": DB_USER,
        "DB_PASSWORD": DB_PASSWORD,
        "KASIR_DISPOSABLE_DB_TEST": "1",
        "SESSION_DRIVER": "file",
        "SESSION_COOKIE": "kasir_bangunan_session",
        "SESSION_PATH": "/kasir-bangunan",
        "SESSION_SECURE_COOKIE": "true",
        "CACHE_STORE": "array",
    }


def app_container_args():
    args = ["--network", NET, "-v", f"{APP}:/app:ro", "-w", "/app"]
    for directory in ("/tmp", "/app/storage/framework/sessions", "/app/storage/framework/views", "/app/storage/framework/cache", "/app/storage/logs", "/app/bootstrap/cache"):
        args += ["--tmpfs", f"{directory}:rw,exec"]
    for key, value in docker_env().items():
        args += ["-e", key]  # Value comes from the child process environment.
    return args


def request(port, method, uri, *, cookie="", fields=None):
    connection = http.client.HTTPConnection("127.0.0.1", port, timeout=8)
    headers = {"Host": "example.test", "X-Forwarded-Proto": "https", "X-Forwarded-Prefix": "/kasir-bangunan"}
    if cookie:
        headers["Cookie"] = cookie
    body = None
    if fields is not None:
        body = urllib.parse.urlencode(fields)
        headers["Content-Type"] = "application/x-www-form-urlencoded"
    try:
        connection.request(method, uri, body=body, headers=headers)
        response = connection.getresponse()
        return response.status, response.getheader("Location", ""), response.getheaders(), response.read().decode("utf-8", "replace")
    finally:
        connection.close()


def verify(port):
    for _ in range(40):
        try:
            status, location, _, _ = request(port, "GET", "/")
            break
        except (ConnectionError, OSError, http.client.HTTPException):
            time.sleep(0.25)
    else:
        raise RuntimeError("HTTP server did not become ready")
    assert status == 302 and location == "https://example.test/kasir-bangunan/login", "guest redirect"
    print("guest redirect: 302, prefixed")

    status, _, headers, html = request(port, "GET", "/login")
    assert status == 200 and 'action="https://example.test/kasir-bangunan/login"' in html, "login form"
    csrf = re.search(r'name="_token" value="([^"]+)"', html)
    assert csrf, "real CSRF token missing"
    cookies = {}

    def receive_cookies(values):
        for key, value in values:
            if key.lower() != "set-cookie":
                continue
            name, data = value.split(";", 1)[0].split("=", 1)
            if name not in ("XSRF-TOKEN", "kasir_bangunan_session"):
                continue
            assert re.search(r"(?i)(?:^|;)\s*path=/kasir-bangunan(?:;|$)", value), "cookie scope"
            assert re.search(r"(?i)(?:^|;)\s*secure(?:;|$)", value), "cookie HTTPS"
            cookies[name] = data

    receive_cookies(headers)
    assert "kasir_bangunan_session" in cookies, "session cookie missing"
    jar = lambda: "; ".join(f"{name}={value}" for name, value in cookies.items())
    print("login form: 200; session path and HTTPS: valid")

    status, location, headers, _ = request(port, "POST", "/products", cookie=jar(), fields={"_token": csrf.group(1), "sku": "SYN-GUEST-01", "name": "Synthetic Guest Product", "unit": "unit", "cost_price": "1.0000", "selling_price": "2.00"})
    assert status == 302 and location == "https://example.test/kasir-bangunan/login", "guest mutation must require login"
    receive_cookies(headers)
    print("guest product mutation: 302 to login")

    status, _, _, _ = request(port, "POST", "/login", cookie=jar(), fields={"email": "cashier@example.test", "password": LOGIN_PASSWORD})
    assert status == 419, "missing CSRF must return 419 (never turn CSRF off)"
    print("missing CSRF: 419")
    # A rejected unsafe request may rotate/invalidate the session; obtain a fresh token.
    status, _, headers, html = request(port, "GET", "/login")
    cookies.clear()
    assert status == 200, "fresh login form after CSRF rejection"
    receive_cookies(headers)
    csrf = re.search(r'name="_token" value="([^"]+)"', html)
    assert csrf, "fresh CSRF field missing"

    status, location, headers, _ = request(port, "POST", "/login", cookie=jar(), fields={"_token": csrf.group(1), "email": "cashier@example.test", "password": LOGIN_PASSWORD})
    receive_cookies(headers)
    assert status == 302 and location.startswith("https://example.test/kasir-bangunan"), f"valid login redirect: HTTP {status}, path={urllib.parse.urlsplit(location).path[:70]}"
    status, _, _, html = request(port, "GET", "/", cookie=jar())
    assert status == 200 and "Kasir (POS)" in html, "authenticated POS page"
    print("real CSRF login: 302; authenticated POS: 200")
    result = command(["docker", "run", "--rm", *app_container_args(), "kasir-mysql-test:local", "php", "-r", 'require "/app/vendor/autoload.php"; $app=require "/app/bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); if (getenv("KASIR_DISPOSABLE_DB_TEST")!=="1" || !preg_match("/^kasir_ephem_db_[0-9a-f]{8}$/", Illuminate\\Support\\Facades\\DB::connection()->getDatabaseName())) exit(3); exit(Illuminate\\Support\\Facades\\DB::table("products")->where("sku", "SYN-GUEST-01")->exists() ? 4 : 0);'], env=os.environ | docker_env())
    assert result.returncode == 0, "guest mutation unexpectedly persisted a product"
    print("guest product persisted: no")


def main():
    assert re.fullmatch(r"kasir_ephem_db_[0-9a-f]{8}", DB_NAME), "disposable database name"
    required(["docker", "network", "create", NET], "create isolated network")
    OWNED.append(("network", NET))
    env = os.environ | {"MARIADB_ROOT_PASSWORD": ROOT_PASSWORD, "MARIADB_DATABASE": DB_NAME, "MARIADB_USER": DB_USER, "MARIADB_PASSWORD": DB_PASSWORD, "MYSQL_PWD": DB_PASSWORD} | docker_env() | {"SYN_PASSWORD": LOGIN_PASSWORD}
    required(["docker", "run", "-d", "--name", DB, "--network", NET, "-e", "MARIADB_ROOT_PASSWORD", "-e", "MARIADB_DATABASE", "-e", "MARIADB_USER", "-e", "MARIADB_PASSWORD", "mariadb:11.4"], "start disposable MariaDB", env=env)
    OWNED.append(("container", DB))
    for _ in range(45):
        if command(["docker", "exec", "-e", "MYSQL_PWD", DB, "mariadb", "-u", DB_USER, DB_NAME, "-e", "SELECT 1"], env=env, timeout=8).returncode == 0:
            break
        time.sleep(1)
    else:
        raise RuntimeError("MariaDB not ready")
    print("disposable MariaDB: ready")

    base = ["docker", "run", "--rm", *app_container_args(), "kasir-mysql-test:local"]
    required([*base, "php", "artisan", "migrate", "--force", "--no-ansi"], "migrate disposable database", env=env)
    seed = ('require "/app/vendor/autoload.php"; $app=require "/app/bootstrap/app.php"; '
            '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
            'if (getenv("KASIR_DISPOSABLE_DB_TEST")!=="1" || !preg_match("/^kasir_ephem_db_[0-9a-f]{8}$/", Illuminate\\Support\\Facades\\DB::connection()->getDatabaseName())) exit(3); '
            'App\\Models\\User::create(["name"=>"Synthetic Cashier","email"=>"cashier@example.test","password"=>Illuminate\\Support\\Facades\\Hash::make(getenv("SYN_PASSWORD"))]);')
    required(["docker", "run", "--rm", *app_container_args(), "-e", "SYN_PASSWORD", "kasir-mysql-test:local", "php", "-r", seed], "seed synthetic user", env=env)
    print("migrations and synthetic user: ready")

    required(["docker", "run", "-d", "--name", WEB, "-p", "127.0.0.1::8000", *app_container_args(), "kasir-mysql-test:local", "php", "-S", "0.0.0.0:8000", "-t", "public"], "start disposable HTTP", env=env)
    OWNED.append(("container", WEB))
    inspection = json.loads(required(["docker", "inspect", WEB], "inspect HTTP container"))[0]
    binding = inspection["NetworkSettings"]["Ports"]["8000/tcp"][0]
    assert binding["HostIp"] == "127.0.0.1", "HTTP port must be loopback-only"
    verify(int(binding["HostPort"]))
    print("MARIADB_HTTP_REHEARSAL_PASS")


if __name__ == "__main__":
    signal.signal(signal.SIGTERM, lambda *_: sys.exit(143))
    try:
        main()
    except (AssertionError, RuntimeError, subprocess.TimeoutExpired, OSError, KeyError, ValueError) as error:
        print(f"REHEARSAL_FAIL: {str(error) if isinstance(error, (AssertionError, RuntimeError)) else type(error).__name__}", file=sys.stderr)
        sys.exit(1)
    finally:
        for kind, name in reversed(OWNED):
            command(["docker", "rm", "-f", name] if kind == "container" else ["docker", "network", "rm", name], timeout=30)
