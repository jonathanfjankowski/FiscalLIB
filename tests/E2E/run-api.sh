#!/usr/bin/env bash
# Launcher da FiscalAPI local (sandbox) para o E2E da FiscalLIB.
set -euo pipefail
cd /c/Projetos/Pessoal/FiscalAPI

get_env() { grep "^$1=" .env | tr -d '\r' | cut -d= -f2-; }

KEK=$(get_env KEK_MASTER_KEY)
export ASPNETCORE_ENVIRONMENT=Development
export ASPNETCORE_URLS="http://localhost:8080"
export ConnectionStrings__Postgres="Host=localhost;Port=5433;Database=fiscal;Username=fiscal;Password=fiscal"
export Certificados__ChaveMestraKEK="$KEK"
export Fiscal__ModoSandbox=true
export ADMIN_EMAIL="$(get_env ADMIN_EMAIL)"
export ADMIN_PASSWORD="$(get_env ADMIN_PASSWORD)"
export ADMIN_JWT_SECRET="$(get_env ADMIN_JWT_SECRET)"

exec /c/Program\ Files/dotnet/dotnet run --project src/Fiscal.Api/Fiscal.Api.csproj -c Debug --no-launch-profile
