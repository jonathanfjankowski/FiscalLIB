#!/usr/bin/env bash
# Launcher do Worker da FiscalAPI local (sandbox) para o E2E da FiscalLIB.
set -euo pipefail
cd /c/Projetos/Pessoal/FiscalAPI

get_env() { grep "^$1=" .env | tr -d '\r' | cut -d= -f2-; }

KEK=$(get_env KEK_MASTER_KEY)
export DOTNET_ENVIRONMENT=Development
export ConnectionStrings__Postgres="Host=localhost;Port=5433;Database=fiscal;Username=fiscal;Password=fiscal"
export Certificados__ChaveMestraKEK="$KEK"
export Fiscal__ModoSandbox=true

exec /c/Program\ Files/dotnet/dotnet run --project src/Fiscal.Worker/Fiscal.Worker.csproj -c Debug --no-launch-profile
